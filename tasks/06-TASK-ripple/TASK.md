# 06 — Ripple: Native WebSocket Server

> Real-time broadcasting for LatticePHP with RFC 6455 WebSockets, channels, auth, and a JavaScript client SDK

## Dependencies
- `lattice/events` (event dispatcher integration for `BroadcastEvent` handling)
- `lattice/http` (auth endpoint for channel authentication)
- `lattice/cache` (Redis connection for pub/sub horizontal scaling)
- `lattice/module` (`#[Module]` attribute for `RippleModule` registration)
- `ext-sockets` (PHP sockets extension for the WebSocket server)
- Optional: `react/event-loop`, `react/socket` (higher-performance event loop)

## Subtasks

### 1. [ ] WebSocket server core — RFC 6455, connection manager, heartbeat/ping-pong

#### WebSocket Protocol Implementation (RFC 6455)
- Implement WebSocket handshake: parse HTTP upgrade request, validate `Sec-WebSocket-Key`, compute `Sec-WebSocket-Accept`, send 101 Switching Protocols response
- Implement frame parser: read frame header (FIN, RSV, opcode, mask, payload length), handle 7-bit, 16-bit, and 64-bit payload length encodings
- Implement frame unmasking: XOR payload with 4-byte masking key (clients MUST mask, server MUST NOT mask)
- Implement frame serialization: construct outbound frames with correct opcode, payload length encoding, no masking
- Handle text frames (opcode 0x1): validate UTF-8 payload, deliver to application layer
- Handle binary frames (opcode 0x2): deliver raw bytes to application layer
- Handle ping frames (opcode 0x9): auto-respond with pong containing the same payload
- Handle pong frames (opcode 0xA): update connection last-activity timestamp
- Handle close frames (opcode 0x8): parse close code and reason, initiate close handshake, respond with close frame, tear down connection
- Handle fragmented messages: buffer continuation frames (opcode 0x0), concatenate payloads, deliver complete message on FIN=1
- Reject frames with reserved opcodes (0x3-0x7, 0xB-0xF) with 1002 Protocol Error
- Reject unmasked client frames with 1002 Protocol Error
- Enforce maximum frame payload size (configurable, default 64 KB) with 1009 Message Too Big
- Optional: implement per-message deflate extension (permessage-deflate) behind config flag
- Unit tests for handshake validation (valid key, missing headers, invalid version)
- Unit tests for frame parsing (text, binary, ping, pong, close, fragmented, oversized)
- Unit tests for frame serialization (all opcodes, various payload sizes)
- Unit tests for masking/unmasking round-trip

#### Connection Manager
- Create `ConnectionManager` class tracking all active WebSocket connections
- Implement `accept(socket)`: perform handshake, create `Connection` object, assign unique connection ID, add to active pool
- Implement `close(connectionId, code, reason)`: send close frame, wait for close response (with timeout), tear down socket, remove from pool
- Implement `forceClose(connectionId)`: immediately close socket without handshake, remove from pool
- Create `Connection` value object: id, socket resource, remote IP, remote port, connected_at, last_activity_at, subscribed channels, auth state
- Implement heartbeat system: send ping frame every N seconds (configurable, default 25s)
- Detect dead connections: if no pong received within 2x heartbeat interval, force-close the connection
- Track connection metadata: bytes sent, bytes received, messages sent, messages received
- Implement `getConnection(id)`, `getAllConnections()`, `getConnectionCount()`, `getConnectionsByChannel(channel)`
- Implement connection limits: max total connections (configurable), max connections per IP (configurable)
- Reject new connections when limits are reached with HTTP 503 before handshake
- Unit tests for connection lifecycle (accept, close, force-close)
- Unit tests for heartbeat and dead connection detection
- Unit tests for connection limits

#### Event Serialization/Deserialization
- Define Ripple wire protocol: JSON-based message format for client-to-server and server-to-client
- Client-to-server: `subscribe`, `unsubscribe`, `whisper` message types
- Server-to-client: event broadcast, `subscription_succeeded`, `subscription_error`, presence member events
- Create `MessageParser` class: parse incoming JSON, validate required fields, return typed message objects
- Create `MessageSerializer` class: serialize outbound messages to JSON
- Reject malformed messages: invalid JSON, missing fields, unknown event types
- Unit tests for all message type parsing and serialization

#### Server Process
- Create `RippleServer` class orchestrating event loop, connection manager, channel manager, message router
- Implement event loop using `ext-sockets`: `socket_create`, `socket_bind`, `socket_listen`, `socket_select`
- Alternative event loop using ReactPHP `React\EventLoop\Loop` and `React\Socket\SocketServer` (selectable via config)
- Accept incoming TCP connections, perform WebSocket handshake, register with ConnectionManager
- Read incoming frames on each loop tick, dispatch to MessageParser
- Route parsed messages: subscribe, unsubscribe, whisper
- Print server startup banner: host, port, PID, max connections, event loop type
- Implement `--debug` flag for verbose frame-level logging

#### Graceful Shutdown and Signal Handling
- Register SIGINT handler (Ctrl+C): initiate graceful shutdown
- Register SIGTERM handler: initiate graceful shutdown
- Graceful shutdown sequence: stop accepting new connections, send close frame to all, wait up to N seconds, force-close remaining, exit
- Register SIGHUP handler: reload configuration without restarting
- Unit tests for signal handling and graceful shutdown sequence

#### RippleServiceProvider and RippleModule
- Create `RippleServiceProvider`: register configuration, bind `BroadcasterInterface` to `RippleBroadcaster`
- Create `RippleModule` with `#[Module]` attribute: register service provider, auth routes, CLI commands
- Register broadcasting auth route: `POST /api/broadcasting/auth`
- Register all CLI commands (`ripple:serve`, `ripple`, `ripple:connections`, `ripple:channels`, `ripple:stats`)
- Publish `config/ripple.php` configuration file
- Unit tests for service provider registration and module route registration

- **Verify:** Server starts on configured port, accepts WebSocket connections, responds to ping with pong, graceful shutdown sends close frames to all clients

### 2. [ ] Channel system — public, private, presence channels

#### ChannelManager
- Create `ChannelManager` class managing channel subscriptions
- Implement `subscribe(connectionId, channelName)`: add connection to channel subscriber set
- Implement `unsubscribe(connectionId, channelName)`: remove connection from channel
- Implement `unsubscribeAll(connectionId)`: remove connection from all channels (on disconnect)
- Implement `getSubscribers(channelName)`, `getSubscriberCount(channelName)`, `getChannels()`, `getChannelsForConnection(connectionId)`

#### Public Channels
- Any channel name not prefixed with `private-` or `presence-` is public
- No authentication required for subscription
- Unit tests for public channel subscribe/unsubscribe

#### Private Channels
- Channel names prefixed with `private-` require authentication before subscription
- Reject unauthenticated subscription attempts with `subscription_error`
- Unit tests for private channel auth enforcement

#### Presence Channels
- Channel names prefixed with `presence-` require authentication and track member metadata
- Track member state: user ID, user info (name, avatar, custom metadata), connection IDs
- On subscribe: add member, broadcast `member_added` to channel, include full member list in `subscription_succeeded`
- On unsubscribe/disconnect: decrement connection count per user, only broadcast `member_removed` when last connection leaves
- `getMembers(channelName)`: return deduplicated member list (one entry per user, not per connection)
- Handle duplicate user IDs (same user on multiple tabs/devices)
- Handle member info updates on reconnection
- Auto-destroy empty channels when last subscriber leaves
- Unit tests for presence member tracking, join/leave events, multi-device handling, channel auto-cleanup

- **Verify:** Public channels accept any subscriber; private channels reject unauthenticated clients; presence channels track members and fire join/leave events correctly

### 3. [ ] Message broadcasting — to channel, to all, to specific connection

- Implement `broadcastToChannel(channelName, eventName, data)`: send to all subscribers of a channel
- Implement `broadcastToAll(eventName, data)`: send to all connected clients
- Implement `broadcastToConnection(connectionId, eventName, data)`: send to a specific connection
- Implement `broadcastToChannelExcept(channelName, eventName, data, excludeConnectionId)`: broadcast excluding sender
- Implement `broadcastToConnections(connectionIds, eventName, data)`: send to a specific set of connections
- Message envelope: `{ "event": "...", "channel": "...", "data": { ... } }`
- Handle send failures gracefully: if send fails (broken pipe), mark connection for cleanup
- Buffer outbound messages: if write buffer is full, queue and flush on next writable event
- Unit tests for broadcast to channel, to all, to specific connection, to channel excluding sender
- Unit tests for send failure handling
- **Verify:** Broadcasting to a channel delivers the message to all subscribers; broadcasting with exclusion skips the sender

### 4. [ ] `php lattice ripple:serve --port=6001` command

- Create `RippleServeCommand` registered as `ripple:serve`
- Accept options: `--host=0.0.0.0`, `--port=6001`, `--max-connections=10000`, `--heartbeat=25`
- Boot `RippleServer` with configured host, port, and options
- Print startup banner with host, port, PID, max connections, event loop type
- Print connection/disconnection log lines to stdout (configurable verbosity)
- Validate configuration on startup: report clear errors for invalid values
- Load configuration from `config/ripple.php`:
  - `ripple.server.host`, `ripple.server.port`, `ripple.server.max_connections`, `ripple.server.heartbeat_interval`
  - `ripple.server.message_max_size`, `ripple.server.allowed_origins`
  - `ripple.ssl.enabled`, `ripple.ssl.cert`, `ripple.ssl.key`
  - `ripple.redis.connection`, `ripple.redis.prefix`
  - `ripple.auth.endpoint`, `ripple.auth.guard`
  - `ripple.rate_limiting.messages_per_second`, `ripple.rate_limiting.connections_per_ip`
- Unit tests for server startup and shutdown
- Integration test: client connects, subscribes, receives broadcast, disconnects
- **Verify:** `php lattice ripple:serve --port=6001` starts a WebSocket server listening on port 6001

### 5. [ ] Auth integration — channel auth via guards, presence member tracking

#### Channel Authentication Endpoint
- Implement auth endpoint handler at `POST /api/broadcasting/auth`
- Receive channel name and socket ID from client, authenticate user via configured guard (session, token, etc.)
- For private channels: check authorization via callbacks, return signed auth token
- For presence channels: check authorization, return signed auth token plus user info (ID, name, custom metadata)
- Auth token format: HMAC-signed string verifiable by Ripple server without HTTP callback
- Support multiple guard types: session-based (cookie), token-based (Bearer), API key
- Return appropriate HTTP errors: 401 for unauthenticated, 403 for unauthorized
- Unit tests for auth endpoint with valid/invalid credentials, private and presence channels

#### Private Channel Authorization Callbacks
- Implement `Ripple::channel('orders.{orderId}', function ($user, $orderId) { ... })` registration syntax
- Support closure-based authorization: return `true`/`false` to allow/deny
- Support class-based authorization: `OrderChannelAuthorizer::class` with `authorize(User $user, int $orderId): bool`
- Pattern matching for channel names: `orders.*`, `users.{userId}.notifications` with wildcard extraction
- Cache authorization results for the duration of the connection
- Unit tests for closure-based and class-based auth, pattern matching, wildcard extraction

#### Client Event Support (Whisper)
- Accept `whisper` messages from clients on private and presence channels only
- Relay whisper to all other subscribers (exclude sender), prefix event names with `client-`
- Reject whisper attempts on public channels
- Rate limit whispers per connection (configurable, default 10/sec)
- Unit tests for whisper relay, rejection on public channels, rate limiting

#### Redis Pub/Sub Adapter for Horizontal Scaling
- Create `RedisPubSubAdapter` bridging Redis pub/sub with local Ripple server
- On server start: subscribe to Redis channel pattern `ripple:channel:*`
- On incoming Redis message: parse and broadcast to locally connected clients
- On local broadcast: publish to Redis for other server instances
- Handle Redis connection loss: auto-reconnect with exponential backoff, re-subscribe
- Publish server presence to Redis with heartbeat key for TUI discovery
- Unit tests for Redis pub/sub message routing, connection loss, and reconnect

- **Verify:** Private channel auth blocks unauthorized users; presence channels track members correctly; whisper messages relay between clients; horizontal scaling delivers messages across server instances

### 6. [ ] `#[Broadcast]` attribute + event integration

#### Broadcasting Integration with lattice/events
- Create `RippleBroadcaster` class implementing `BroadcasterInterface`
- On `dispatch(BroadcastEvent $event)`: serialize event data, determine target channels from `broadcastOn()`, publish to Redis pub/sub
- Register event listener in `lattice/events` intercepting `BroadcastEvent` events
- Support `broadcastAs()` for custom event names (default to class name)
- Support `broadcastWith()` for custom payload (default to all public properties)
- Support `broadcastWhen()` for conditional broadcasting
- Queue broadcasting: option to dispatch via queue for non-blocking HTTP responses
- Unit tests for event serialization, Redis publishing, conditional broadcasting

#### `#[Broadcast]` Attribute
- Create `#[Broadcast]` PHP attribute with parameters: `channel` (string, `{property}` interpolation), `as` (event name), `when` (optional callable)
- Implement attribute reader: scan event class for `#[Broadcast]`, extract channel pattern and event name
- Interpolate channel name: replace `{property}` placeholders with event instance property values
- Register attribute-based events in the broadcast listener (same pathway as `BroadcastEvent` interface)
- Unit tests for attribute parsing, channel interpolation, end-to-end broadcasting

- **Verify:** Dispatching an event implementing `BroadcastEvent` delivers to subscribed WebSocket clients; `#[Broadcast]` attribute on an event class broadcasts without implementing the interface; channel interpolation resolves correctly

### 7. [ ] JavaScript client SDK (lattice-ripple.js) — connect, subscribe, listen, whisper

#### Core Client
- Create npm package `lattice-ripple` with TypeScript source
- Implement `Ripple` class: constructor accepts `{ host, auth: { endpoint, headers } }`
- Automatic WebSocket connection on first channel call or explicit `connect()`
- Automatic reconnection: exponential backoff (1s, 2s, 4s, 8s, max 30s), configurable max retries
- Implement `disconnect()`: close WebSocket, clean up all state
- Implement `on(event, callback)`: global events (`connected`, `disconnected`, `reconnecting`, `error`)

#### Channel API
- `channel(name)` returns `Channel` object for public channels
- `private(name)` returns `PrivateChannel`, auto-prefixes `private-`, authenticates before subscribing
- `presence(name)` returns `PresenceChannel`, auto-prefixes `presence-`, authenticates before subscribing
- `Channel.listen(event, callback)`: register callback for specific event
- `Channel.stopListening(event, callback?)`: remove event listener
- `PrivateChannel.whisper(event, data)`: send client event
- `PresenceChannel.here(callback)`: current member list on subscription
- `PresenceChannel.joining(callback)`: `member_added` events
- `PresenceChannel.leaving(callback)`: `member_removed` events
- `leave(channelName)`: unsubscribe, clean up listeners

#### Authentication Flow
- Before subscribing to private/presence channels, POST to auth endpoint with channel name and socket ID
- Include configured headers (e.g., Authorization Bearer token)
- Cache auth tokens per channel for the lifetime of the connection
- Handle auth failure: emit `subscription_error`, do not retry automatically

#### Build and Distribution
- Bundle as ESM and CJS with TypeScript declarations
- Minified production build (target: < 10 KB gzipped)

#### Echo-Compatible API Surface
- Ensure `Ripple` class method signatures match `laravel-echo` `Echo` class
- Ensure channel class signatures match Echo: `listen()`, `stopListening()`, `whisper()`, `here()`, `joining()`, `leaving()`
- Support `.event-name` notation for Pusher/Echo convention compatibility

- Unit tests for connection lifecycle, channel subscription, authentication flow, presence member tracking
- **Verify:** Browser client connects to Ripple server, subscribes to a private channel, receives broadcast events, and whisper messages relay between clients

### 8. [ ] CLI TUI — `php lattice ripple` live connection stats + non-interactive commands

#### Interactive TUI (`php lattice ripple`)
- Create `php lattice ripple` command as interactive TUI entry point
- Connect to running Ripple server via admin socket or Redis metadata keys
- Dashboard view (refreshes every 1 second):
  - Total connected clients (number, +/- delta per second)
  - Active channels (count)
  - Messages per second (inbound and outbound)
  - Server memory usage (MB)
  - Server uptime
- Connected clients list view: scrollable table
  - Columns: Connection ID, Remote IP, Connected Duration, Channels, Messages Sent, Messages Received
  - Color-coded by activity (green = active recently, gray = idle)
  - Arrow key navigation, `/` to search/filter
- Channel list view: scrollable table
  - Columns: Channel Name, Type (public/private/presence), Subscribers, Messages/sec
  - Sorted by subscriber count descending
  - Expand a channel to see its subscriber list
  - Color-coded channel types: public = blue, private = yellow, presence = green
- Keyboard shortcuts: `q` quit, `r` refresh, `Tab` switch views, `1` dashboard, `2` connections, `3` channels, `/` search, `?` help
- Compact mode for narrow terminals (< 80 columns)

#### Non-Interactive CLI Commands
- `php lattice ripple:connections` — print connected clients as formatted table, exit
  - Support `--json` flag, `--count` flag
- `php lattice ripple:channels` — print active channels with subscriber counts, exit
  - Support `--json` flag, `--type=private` filter
- `php lattice ripple:stats` — print server stats (connections, channels, messages/sec, memory, uptime), exit
  - Support `--json` flag, `--watch` flag for continuous output
- Unit tests for all non-interactive commands and `--json` output format

- **Verify:** `php lattice ripple` displays live connection and channel stats from a running server; non-interactive commands output correct data in both table and JSON formats

## Integration Verification
- [ ] WebSocket client connects to `ws://localhost:6001` and receives a connection acknowledgement
- [ ] Client subscribes to a public channel and receives broadcast messages
- [ ] Client subscribes to a private channel; unauthenticated client is rejected
- [ ] Presence channel tracks members; `member_added` and `member_removed` fire correctly
- [ ] `#[Broadcast]` attribute on an event class delivers to subscribed clients when the event is dispatched
- [ ] Whisper message from one client relays to other subscribers on the same private channel
- [ ] Two Ripple server instances share channels via Redis pub/sub
- [ ] `php lattice ripple` TUI shows live stats from a running server
- [ ] Graceful shutdown sends close frames to all connected clients
- [ ] JavaScript client auto-reconnects after server restart and re-subscribes to channels
