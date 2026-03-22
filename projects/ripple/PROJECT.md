# Ripple -- Native WebSocket Server for LatticePHP

## Overview

Ripple is LatticePHP's native WebSocket server -- events ripple through the lattice to connected clients. It serves the same role as **Laravel Reverb** for Laravel: a first-party, PHP-native real-time broadcasting layer that eliminates the need for third-party WebSocket services like Pusher, Ably, or Soketi.

Ripple is the **real-time backbone** of the entire LatticePHP ecosystem. Every other Lattice tool that needs live updates -- Chronos workflow events, Loom job progress, Nightwatch log tailing, Prism error streaming -- broadcasts through Ripple. Rather than each package building its own SSE or polling mechanism, Ripple provides a single, shared WebSocket transport that all packages publish to.

### What Ripple Does

- **Runs a persistent WebSocket server** that accepts client connections over `ws://` or `wss://`
- **Implements the full WebSocket protocol** (RFC 6455) with ping/pong heartbeat, fragmented frames, and graceful close
- **Provides a channel system** -- public, private, and presence channels -- for organized message routing
- **Authenticates channel subscriptions** via LatticePHP guards, ensuring private channels are access-controlled
- **Broadcasts application events** from any LatticePHP process to all subscribed WebSocket clients in real-time
- **Scales horizontally** via Redis pub/sub, so multiple Ripple server instances share a unified channel namespace
- **Ships a JavaScript client SDK** (`lattice-ripple.js`) for browser-based subscriptions with an Echo-compatible API surface
- **Includes a CLI TUI** (`php lattice ripple`) for live monitoring of connections, channels, and message throughput

---

## Architecture

### Two Processes

Ripple consists of two distinct runtime modes:

1. **The Server Process** (`php lattice ripple:serve`) -- a long-running PHP process that listens on a TCP socket, performs the WebSocket handshake, manages connections, and routes messages between channels and clients. Built on PHP's `ext-sockets` extension with an optional ReactPHP event loop for higher concurrency.

2. **The CLI TUI** (`php lattice ripple`) -- an interactive terminal dashboard that connects to the running Ripple server (via its internal admin socket or Redis) and displays live stats: connection count, active channels, messages per second, memory usage, and a scrollable list of connected clients with their channel subscriptions.

### Server Internals

```
+------------------+       +-------------------+       +------------------+
|   PHP App        |       |   Ripple Server   |       |   Browser /      |
|   (HTTP request  | ----> |   (long-running   | <---> |   JS Client      |
|    or queue job) |       |    PHP process)   |       |   (WebSocket)    |
|                  |       |                   |       |                  |
|  BroadcastEvent  |       |  Connection Mgr   |       |  lattice-ripple  |
|  publishes to    |       |  Channel Registry |       |  .js SDK         |
|  Redis pub/sub   |       |  Message Router   |       |                  |
+------------------+       +-------------------+       +------------------+
         |                          |
         v                          v
+------------------+       +-------------------+
|   Redis          |       |   Redis           |
|   Pub/Sub        | <---> |   Pub/Sub         |
|   (horizontal    |       |   (receive from   |
|    scaling)      |       |    other servers)  |
+------------------+       +-------------------+
```

### Channel System

Ripple supports three channel types, matching the conventions established by Laravel Broadcasting and Pusher:

| Channel Type | Prefix       | Auth Required | Use Case                                         |
|--------------|--------------|---------------|--------------------------------------------------|
| **Public**   | (none)       | No            | Open broadcasts: news feeds, public notifications |
| **Private**  | `private-`   | Yes           | User-specific data: order updates, notifications  |
| **Presence** | `presence-`  | Yes           | Who's online: collaborative editing, chat rooms   |

**Private channels** require the client to authenticate by calling a server-side authorization endpoint. The server checks the user's identity (via LatticePHP guards) and returns a signed auth token. The Ripple server validates this token before allowing the subscription.

**Presence channels** extend private channels with member tracking. When a user subscribes to a presence channel, Ripple broadcasts a `member_added` event to all existing subscribers. When they unsubscribe (or disconnect), a `member_removed` event fires. The full member list is available at any time.

### Horizontal Scaling via Redis Pub/Sub

A single Ripple server instance can handle thousands of concurrent connections. For higher scale or redundancy, multiple Ripple instances can run behind a load balancer. They coordinate via Redis pub/sub:

- When a PHP application broadcasts an event, it publishes to a Redis channel (e.g., `ripple:channel:orders.42`).
- Every Ripple server instance subscribes to all active Redis channels.
- When a Redis message arrives, the Ripple server forwards it to its locally connected clients on the matching channel.

This means the application code never connects directly to a Ripple server -- it always publishes through Redis. The Ripple servers are stateless relays (aside from their in-memory connection tables).

---

## Broadcasting Integration

### The `BroadcastEvent` Interface

Any LatticePHP event class can implement the `BroadcastEvent` interface to opt into broadcasting:

```php
use Lattice\Ripple\Contracts\BroadcastEvent;

class OrderShipped implements BroadcastEvent
{
    public function __construct(
        public readonly int $orderId,
        public readonly string $trackingNumber,
    ) {}

    public function broadcastOn(): array
    {
        return ['private-orders.' . $this->orderId];
    }

    public function broadcastAs(): string
    {
        return 'order.shipped';
    }

    public function broadcastWith(): array
    {
        return [
            'order_id' => $this->orderId,
            'tracking' => $this->trackingNumber,
        ];
    }
}
```

### The `#[Broadcast]` Attribute

For convenience, events can use the `#[Broadcast]` attribute instead of implementing the interface:

```php
use Lattice\Ripple\Attributes\Broadcast;

#[Broadcast(channel: 'private-orders.{orderId}', as: 'order.shipped')]
class OrderShipped
{
    public function __construct(
        public readonly int $orderId,
        public readonly string $trackingNumber,
    ) {}
}
```

When an event with `#[Broadcast]` is dispatched through `lattice/events`, the Ripple integration layer automatically serializes it and publishes to Redis.

### Client Events (Whisper)

Ripple supports client-to-client events ("whisper") on private and presence channels. A whisper is sent from one client to all other subscribers of the same channel without touching the server-side application. Use cases include typing indicators, cursor positions, and other ephemeral state.

---

## Ecosystem Integration

Ripple is the real-time transport for the entire Lattice ecosystem:

| Package           | Ripple Channel                  | Events                                          |
|-------------------|---------------------------------|-------------------------------------------------|
| **Chronos**       | `private-chronos.{workflowId}`  | Workflow status changes, new events, stats. Chronos uses SSE by default; optionally uses Ripple channels for WebSocket-based real-time. |
| **Loom**          | `private-loom.{queueName}`      | Job dispatched, completed, failed, worker status. Loom uses SSE by default; optionally uses Ripple channels for WebSocket-based real-time. |
| **Nightwatch**    | `private-nightwatch.{stream}`   | Log entries in real-time (tail mode). Nightwatch uses SSE by default; optionally uses Ripple channels for WebSocket-based real-time. |
| **Prism**         | `private-prism.{projectId}`     | New errors, issue status changes, live feed      |

Each package publishes `BroadcastEvent` instances. Ripple handles the transport. The JavaScript client SDK or CLI TUI subscribes on the other end.

---

## JavaScript Client SDK

`lattice-ripple.js` is a lightweight browser/Node.js client for connecting to a Ripple WebSocket server:

```javascript
import { Ripple } from 'lattice-ripple';

const ripple = new Ripple({
    host: 'ws://localhost:6001',
    auth: {
        endpoint: '/api/broadcasting/auth',
        headers: { 'Authorization': 'Bearer ...' },
    },
});

ripple.private('orders.42')
    .listen('order.shipped', (event) => {
        console.log('Shipped!', event.tracking);
    });

ripple.presence('chat.room.1')
    .here((members) => console.log('Members:', members))
    .joining((member) => console.log('Joined:', member))
    .leaving((member) => console.log('Left:', member))
    .listen('message', (event) => console.log(event));
```

### Echo-Compatible API Surface

The SDK exposes an API surface compatible with `laravel-echo`, so users migrating from Laravel can swap `Echo` for `Ripple` with minimal changes. The `channel()`, `private()`, `presence()`, `listen()`, `whisper()`, and `leave()` methods all follow the same signatures.

---

## PHP Server-to-Server Client

For server-side broadcasting without going through the event system (e.g., from a microservice or external process), Ripple provides a PHP client:

```php
use Lattice\Ripple\Client\RippleClient;

$client = new RippleClient(redisConnection: $redis);
$client->broadcast('private-orders.42', 'order.shipped', [
    'order_id' => 42,
    'tracking' => 'TRACK123',
]);
```

This writes directly to Redis pub/sub, bypassing the event dispatcher.

---

## Configuration

```php
// config/ripple.php
return [
    'server' => [
        'host' => '0.0.0.0',
        'port' => 6001,
        'max_connections' => 10000,
        'heartbeat_interval' => 25, // seconds
        'message_max_size' => 64 * 1024, // 64 KB
        'allowed_origins' => ['*'],
    ],
    'ssl' => [
        'enabled' => false,
        'cert' => null,
        'key' => null,
    ],
    'redis' => [
        'connection' => 'default',
        'prefix' => 'ripple:',
    ],
    'auth' => [
        'endpoint' => '/api/broadcasting/auth',
        'guard' => 'web',
    ],
    'rate_limiting' => [
        'messages_per_second' => 100,
        'connections_per_ip' => 10,
    ],
];
```

---

## CLI TUI

The interactive TUI (`php lattice ripple`) provides a live dashboard for the running Ripple server:

- **Connection count**: total connected clients, connections/disconnections per second
- **Channel list**: active channels with subscriber counts, messages per second per channel
- **Message throughput**: global messages/sec, inbound vs outbound
- **Memory usage**: server process memory consumption
- **Connected clients list**: scrollable table of all clients with their IP, connected duration, subscribed channels
- **Keyboard navigation**: arrow keys to scroll, `/` to search, `q` to quit

Non-interactive commands are also available for scripting:

- `php lattice ripple:connections` -- print connected clients as a table
- `php lattice ripple:channels` -- print channels with subscriber counts
- `php lattice ripple:stats` -- print server stats (connections, messages, memory)

---

## Dependencies

| Package          | Purpose                                                    |
|------------------|------------------------------------------------------------|
| `lattice/events` | Event dispatcher integration for `BroadcastEvent` handling |
| `lattice/http`   | Auth endpoint for channel authentication                   |
| `lattice/cache`  | Redis connection for pub/sub horizontal scaling            |
| `lattice/module` | `#[Module]` attribute for `RippleModule` registration      |
| `ext-sockets`    | PHP sockets extension for the WebSocket server             |

### Optional

| Package          | Purpose                                              |
|------------------|------------------------------------------------------|
| `react/event-loop` | Higher-performance event loop (alternative to `ext-sockets` blocking loop) |
| `react/socket`     | ReactPHP socket server for non-blocking I/O        |

---

## Design Inspiration

### Laravel Reverb
- Native PHP WebSocket server (no Node.js dependency)
- Channel types: public, private, presence
- Pusher-compatible protocol for interoperability with existing client SDKs
- Horizontal scaling via Redis

### Key Design Principles
- **Zero external dependencies at minimum**: Works with just `ext-sockets` and Redis. ReactPHP is optional for higher throughput.
- **Protocol-correct**: Full RFC 6455 compliance including fragmented frames, binary messages, ping/pong, and close handshake.
- **Ecosystem-first**: Ripple exists to serve the entire Lattice ecosystem, not just user-facing apps. Every Lattice package with real-time needs uses Ripple.
- **Observable**: The TUI makes the server visible. You should never wonder "is the WebSocket server working?" -- just run `php lattice ripple` and see.
