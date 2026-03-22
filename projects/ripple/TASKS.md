# Ripple -- Task List

---

## Phase 1 -- WebSocket Server Core

### WebSocket Protocol Implementation (RFC 6455)

- [ ] Implement WebSocket handshake: parse HTTP upgrade request, validate `Sec-WebSocket-Key`, compute `Sec-WebSocket-Accept`, send 101 Switching Protocols response
- [ ] Implement frame parser: read frame header (FIN, RSV, opcode, mask, payload length), handle 7-bit, 16-bit, and 64-bit payload length encodings
- [ ] Implement frame unmasking: XOR payload with 4-byte masking key (clients MUST mask, server MUST NOT mask)
- [ ] Implement frame serialization: construct outbound frames with correct opcode, payload length encoding, and no masking
- [ ] Handle text frames (opcode 0x1): validate UTF-8 payload, deliver to application layer
- [ ] Handle binary frames (opcode 0x2): deliver raw bytes to application layer
- [ ] Handle ping frames (opcode 0x9): auto-respond with pong containing the same payload
- [ ] Handle pong frames (opcode 0xA): update connection last-activity timestamp
- [ ] Handle close frames (opcode 0x8): parse close code and reason, initiate close handshake, respond with close frame, tear down connection
- [ ] Handle fragmented messages: buffer continuation frames (opcode 0x0), concatenate payloads, deliver complete message on FIN=1
- [ ] Reject frames with reserved opcodes (0x3-0x7, 0xB-0xF) -- close connection with 1002 Protocol Error
- [ ] Reject unmasked client frames -- close connection with 1002 Protocol Error
- [ ] Enforce maximum frame payload size (configurable, default 64 KB) -- close connection with 1009 Message Too Big
- [ ] Implement per-message deflate extension (permessage-deflate) negotiation and compression/decompression -- optional, behind config flag
- [ ] Unit tests for handshake validation (valid key, missing headers, invalid version)
- [ ] Unit tests for frame parsing (text, binary, ping, pong, close, fragmented, oversized)
- [ ] Unit tests for frame serialization (all opcodes, various payload sizes)
- [ ] Unit tests for masking/unmasking round-trip

### Connection Manager

- [ ] Create `ConnectionManager` class that tracks all active WebSocket connections
- [ ] Implement `accept(socket)` -- perform handshake, create `Connection` object, assign unique connection ID, add to active pool
- [ ] Implement `close(connectionId, code, reason)` -- send close frame, wait for close response (with timeout), tear down socket, remove from pool
- [ ] Implement `forceClose(connectionId)` -- immediately close socket without handshake, remove from pool
- [ ] Create `Connection` value object: id, socket resource, remote IP, remote port, connected_at, last_activity_at, subscribed channels, auth state (user ID, metadata)
- [ ] Implement heartbeat system: send ping frame to each connection every N seconds (configurable, default 25s)
- [ ] Detect dead connections: if no pong received within 2x heartbeat interval, force-close the connection
- [ ] Track connection metadata: bytes sent, bytes received, messages sent, messages received
- [ ] Implement `getConnection(id)`, `getAllConnections()`, `getConnectionCount()`, `getConnectionsByChannel(channel)`
- [ ] Implement connection limits: max total connections (configurable), max connections per IP (configurable)
- [ ] Reject new connections when limits are reached -- return HTTP 503 before handshake
- [ ] Unit tests for connection lifecycle (accept, close, force-close)
- [ ] Unit tests for heartbeat and dead connection detection
- [ ] Unit tests for connection limits

### Channel System

- [ ] Create `ChannelManager` class that manages channel subscriptions
- [ ] Implement `subscribe(connectionId, channelName)` -- add connection to channel subscriber set, return success/failure
- [ ] Implement `unsubscribe(connectionId, channelName)` -- remove connection from channel subscriber set
- [ ] Implement `unsubscribeAll(connectionId)` -- remove connection from all channels (used on disconnect)
- [ ] Implement `getSubscribers(channelName)` -- return list of connection IDs subscribed to channel
- [ ] Implement `getSubscriberCount(channelName)` -- return count of subscribers
- [ ] Implement `getChannels()` -- return list of all active channels (channels with at least one subscriber)
- [ ] Implement `getChannelsForConnection(connectionId)` -- return channels a specific connection is subscribed to
- [ ] Support **public channels**: any name not prefixed with `private-` or `presence-` -- no authentication required
- [ ] Support **private channels**: name prefixed with `private-` -- require authentication before subscription is accepted
- [ ] Support **presence channels**: name prefixed with `presence-` -- require authentication, track member metadata (user ID, user info)
- [ ] Presence channel: `getMembers(channelName)` -- return list of member user IDs and metadata
- [ ] Presence channel: broadcast `member_added` event to all existing subscribers when a new member joins
- [ ] Presence channel: broadcast `member_removed` event when a member unsubscribes or disconnects
- [ ] Presence channel: handle duplicate user IDs (same user on multiple tabs/devices) -- track connection count per user, only fire `member_removed` when all connections for that user disconnect
- [ ] Auto-destroy empty channels: when last subscriber leaves, clean up channel state
- [ ] Unit tests for public channel subscribe/unsubscribe
- [ ] Unit tests for private channel (reject unauthenticated, accept authenticated)
- [ ] Unit tests for presence channel (member tracking, join/leave events, duplicate user handling)
- [ ] Unit tests for channel auto-cleanup

### Message Broadcasting

- [ ] Implement `broadcastToChannel(channelName, eventName, data)` -- send message to all subscribers of a channel
- [ ] Implement `broadcastToAll(eventName, data)` -- send message to all connected clients
- [ ] Implement `broadcastToConnection(connectionId, eventName, data)` -- send message to a specific connection
- [ ] Implement `broadcastToChannelExcept(channelName, eventName, data, excludeConnectionId)` -- broadcast to channel excluding sender (for whisper relay)
- [ ] Implement `broadcastToConnections(connectionIds, eventName, data)` -- send to a specific set of connections
- [ ] Message serialization: wrap all outbound messages in a JSON envelope: `{ "event": "...", "channel": "...", "data": { ... } }`
- [ ] Handle send failures gracefully: if a send fails (broken pipe), mark connection for cleanup rather than crashing the server
- [ ] Buffer outbound messages: if a connection's write buffer is full, queue messages and flush on next writable event
- [ ] Unit tests for broadcast to channel, to all, to specific connection, to channel excluding sender
- [ ] Unit tests for send failure handling

### Event Serialization/Deserialization

- [ ] Define the Ripple wire protocol: JSON-based message format for client-to-server and server-to-client messages
- [ ] Client-to-server messages: `{ "event": "subscribe", "data": { "channel": "..." } }`, `{ "event": "unsubscribe", "data": { "channel": "..." } }`, `{ "event": "whisper", "data": { "channel": "...", "event": "...", "data": { ... } } }`
- [ ] Server-to-client messages: `{ "event": "...", "channel": "...", "data": { ... } }`, `{ "event": "subscription_succeeded", "channel": "..." }`, `{ "event": "subscription_error", "channel": "...", "data": { "error": "..." } }`
- [ ] Presence-specific server-to-client messages: `{ "event": "member_added", "channel": "presence-...", "data": { "user_id": ..., "user_info": { ... } } }`, `{ "event": "member_removed", ... }`, `{ "event": "subscription_succeeded", "channel": "presence-...", "data": { "members": [...] } }`
- [ ] Implement `MessageParser` class: parse incoming JSON, validate required fields, return typed message objects
- [ ] Implement `MessageSerializer` class: serialize outbound message objects to JSON strings
- [ ] Reject malformed messages: invalid JSON, missing required fields, unknown event types -- send error response, do not crash
- [ ] Unit tests for parsing all client-to-server message types
- [ ] Unit tests for serializing all server-to-client message types
- [ ] Unit tests for malformed message handling

### Server Process

- [ ] Create `php lattice ripple:serve` command -- starts the WebSocket server
- [ ] Accept options: `--host=0.0.0.0`, `--port=6001`, `--max-connections=10000`, `--heartbeat=25`
- [ ] Create `RippleServer` class that orchestrates the event loop, connection manager, channel manager, and message router
- [ ] Implement event loop using `ext-sockets`: `socket_create`, `socket_bind`, `socket_listen`, `socket_select` (non-blocking multiplexing)
- [ ] Alternative event loop using ReactPHP `React\EventLoop\Loop` and `React\Socket\SocketServer` for higher performance -- selectable via config
- [ ] Accept incoming TCP connections, perform WebSocket handshake, register with ConnectionManager
- [ ] Read incoming frames from all connections on each loop tick, dispatch to MessageParser
- [ ] Route parsed messages: subscribe, unsubscribe, whisper -- delegate to ChannelManager and broadcaster
- [ ] Print server startup banner: host, port, PID, max connections, event loop type
- [ ] Print connection/disconnection log lines to stdout (configurable verbosity)
- [ ] Implement `--debug` flag for verbose frame-level logging
- [ ] Unit tests for server startup and shutdown
- [ ] Integration test: client connects, subscribes, receives broadcast, disconnects

### Graceful Shutdown and Signal Handling

- [ ] Register SIGINT handler (Ctrl+C): initiate graceful shutdown
- [ ] Register SIGTERM handler: initiate graceful shutdown
- [ ] Graceful shutdown sequence: stop accepting new connections, send close frame to all existing connections, wait up to N seconds for close handshakes, force-close remaining connections, exit
- [ ] Print shutdown progress to stdout: "Closing N connections...", "Shutdown complete."
- [ ] Register SIGHUP handler: reload configuration without restarting (re-read config file, apply new limits)
- [ ] Unit tests for signal handling and graceful shutdown sequence

### RippleServiceProvider and RippleModule

- [ ] Create `RippleServiceProvider` -- registers configuration, binds broadcaster interface to Ripple implementation
- [ ] Create `RippleModule` with `#[Module]` attribute -- registers service provider, auth routes, and CLI commands
- [ ] Register broadcasting auth route: `POST /api/broadcasting/auth` -- validates user identity via configured guard, returns signed auth response for private/presence channels
- [ ] Bind `BroadcasterInterface` to `RippleBroadcaster` -- the Ripple implementation that publishes to Redis pub/sub
- [ ] Register `ripple:serve` Artisan/Lattice command
- [ ] Register `ripple` (TUI), `ripple:connections`, `ripple:channels`, `ripple:stats` commands
- [ ] Publish `config/ripple.php` configuration file
- [ ] Unit tests for service provider registration and configuration binding
- [ ] Unit tests for module route registration

---

## Phase 2 -- Integration

### Channel Authentication via LatticePHP Guards

- [ ] Implement auth endpoint handler: receive channel name and socket ID from client, authenticate user via configured guard (session, token, etc.)
- [ ] For private channels: check if user is authorized for the channel (via authorization callbacks), return signed auth token
- [ ] For presence channels: check authorization, return signed auth token plus user info (ID, name, custom metadata)
- [ ] Auth token format: HMAC-signed string that the Ripple server can verify without calling back to the HTTP app
- [ ] Support multiple guard types: session-based (cookie), token-based (Bearer), API key
- [ ] Return appropriate HTTP errors: 401 for unauthenticated, 403 for unauthorized
- [ ] Unit tests for auth endpoint with valid and invalid credentials
- [ ] Unit tests for private channel authorization
- [ ] Unit tests for presence channel authorization with user info

### Private Channel Authorization Callbacks

- [ ] Implement `Ripple::channel('orders.{orderId}', function ($user, $orderId) { ... })` registration syntax
- [ ] Support closure-based authorization: return `true`/`false` to allow/deny
- [ ] Support class-based authorization: `OrderChannelAuthorizer::class` with `authorize(User $user, int $orderId): bool`
- [ ] Pattern matching for channel names: `orders.*`, `users.{userId}.notifications` -- extract wildcards as callback parameters
- [ ] Cache authorization results for the duration of the connection (don't re-authorize on every message)
- [ ] Unit tests for closure-based and class-based authorization
- [ ] Unit tests for pattern matching and wildcard extraction

### Presence Channel Member Tracking

- [ ] Track member state in `ChannelManager`: user ID, user info (name, avatar, custom metadata), connection IDs
- [ ] On subscribe: add member, broadcast `member_added` to channel, include full member list in `subscription_succeeded` response
- [ ] On unsubscribe/disconnect: decrement connection count for user, only broadcast `member_removed` when last connection for that user leaves
- [ ] `getMembers(channelName)` returns deduplicated member list (one entry per user, not per connection)
- [ ] `getMemberCount(channelName)` returns count of unique users
- [ ] Handle member info updates: if same user reconnects with different info, update and broadcast `member_updated`
- [ ] Unit tests for member join/leave lifecycle
- [ ] Unit tests for multi-device handling (same user, multiple connections)
- [ ] Unit tests for member list accuracy

### Broadcasting Integration with lattice/events

- [ ] Create `RippleBroadcaster` class implementing `BroadcasterInterface`
- [ ] On `dispatch(BroadcastEvent $event)`: serialize event data, determine target channels from `broadcastOn()`, publish to Redis pub/sub
- [ ] Register event listener in `lattice/events` that intercepts events implementing `BroadcastEvent` and delegates to `RippleBroadcaster`
- [ ] Support `broadcastAs()` for custom event names (default to class name)
- [ ] Support `broadcastWith()` for custom payload (default to all public properties)
- [ ] Support `broadcastWhen()` for conditional broadcasting (return `false` to suppress)
- [ ] Queue broadcasting: option to dispatch broadcast via queue for non-blocking HTTP responses
- [ ] Unit tests for event serialization and Redis publishing
- [ ] Unit tests for conditional broadcasting
- [ ] Integration test: dispatch event -> Redis pub/sub -> Ripple server -> client receives

### `#[Broadcast]` Attribute on Events

- [ ] Create `#[Broadcast]` PHP attribute with parameters: `channel` (string, supports `{property}` interpolation), `as` (string, event name), `when` (optional callable)
- [ ] Implement attribute reader: scan event class for `#[Broadcast]`, extract channel pattern and event name
- [ ] Interpolate channel name: replace `{property}` placeholders with actual property values from the event instance
- [ ] Register attribute-based events in the broadcast event listener (same pathway as `BroadcastEvent` interface)
- [ ] Unit tests for attribute parsing and channel interpolation
- [ ] Unit tests for attribute-based broadcasting end-to-end

### Client Event Support (Whisper)

- [ ] Accept `whisper` messages from clients on private and presence channels only
- [ ] Relay whisper to all other subscribers of the same channel (exclude sender)
- [ ] Prefix whisper event names with `client-` to distinguish from server events
- [ ] Reject whisper attempts on public channels -- send error response
- [ ] Rate limit whispers per connection (configurable, default 10/sec)
- [ ] Unit tests for whisper relay
- [ ] Unit tests for whisper rejection on public channels
- [ ] Unit tests for whisper rate limiting

### Redis Pub/Sub Adapter for Horizontal Scaling

- [ ] Create `RedisPubSubAdapter` class that bridges Redis pub/sub with the local Ripple server
- [ ] On server start: subscribe to Redis channel pattern `ripple:channel:*` for all broadcast messages
- [ ] On incoming Redis message: parse channel name and event data, broadcast to locally connected clients on the matching channel
- [ ] On local broadcast (from connected client whisper or internal event): publish to Redis so other server instances receive it
- [ ] Handle Redis connection loss: auto-reconnect with exponential backoff, re-subscribe to all channels
- [ ] Handle Redis reconnection: replay any missed messages using a small buffer or accept message loss with a warning log
- [ ] Publish server presence to Redis: register this server instance with a heartbeat key so the TUI can discover all instances
- [ ] Unit tests for Redis pub/sub message routing
- [ ] Integration tests with multiple server instances (simulate with in-process)
- [ ] Unit tests for Redis connection loss and reconnect

### Configuration

- [ ] `ripple.server.host` -- bind address (default `0.0.0.0`)
- [ ] `ripple.server.port` -- listen port (default `6001`)
- [ ] `ripple.server.max_connections` -- maximum simultaneous connections (default `10000`)
- [ ] `ripple.server.heartbeat_interval` -- seconds between ping frames (default `25`)
- [ ] `ripple.server.message_max_size` -- maximum message payload in bytes (default `65536`)
- [ ] `ripple.server.allowed_origins` -- array of allowed Origin headers for CORS (default `['*']`)
- [ ] `ripple.ssl.enabled` -- enable TLS (default `false`)
- [ ] `ripple.ssl.cert` -- path to SSL certificate file
- [ ] `ripple.ssl.key` -- path to SSL private key file
- [ ] `ripple.redis.connection` -- Redis connection name from lattice cache config (default `default`)
- [ ] `ripple.redis.prefix` -- key prefix for Redis pub/sub channels (default `ripple:`)
- [ ] `ripple.auth.endpoint` -- HTTP path for channel authentication (default `/api/broadcasting/auth`)
- [ ] `ripple.auth.guard` -- LatticePHP guard to use for authentication (default `web`)
- [ ] `ripple.rate_limiting.messages_per_second` -- max messages per second per connection (default `100`)
- [ ] `ripple.rate_limiting.connections_per_ip` -- max connections from a single IP (default `10`)
- [ ] Validate configuration on server start -- report clear errors for invalid values
- [ ] Unit tests for configuration loading and validation

---

## Phase 3 -- Client Libraries

### JavaScript Client SDK (`lattice-ripple.js`)

- [ ] Create npm package `lattice-ripple` with TypeScript source
- [ ] Implement `Ripple` class: constructor accepts `{ host, auth: { endpoint, headers } }`, manages a single WebSocket connection
- [ ] Implement automatic WebSocket connection: connect on first `channel()`/`private()`/`presence()` call, or explicit `connect()`
- [ ] Implement automatic reconnection: exponential backoff (1s, 2s, 4s, 8s, max 30s), configurable max retries
- [ ] Implement `channel(name)` -- return a `Channel` object for a public channel
- [ ] Implement `private(name)` -- return a `PrivateChannel` object, automatically prefix `private-`, authenticate before subscribing
- [ ] Implement `presence(name)` -- return a `PresenceChannel` object, automatically prefix `presence-`, authenticate before subscribing
- [ ] `Channel.listen(event, callback)` -- register callback for a specific event on this channel
- [ ] `Channel.stopListening(event, callback?)` -- remove event listener
- [ ] `PrivateChannel.whisper(event, data)` -- send client event to channel
- [ ] `PresenceChannel.here(callback)` -- receive current member list on subscription
- [ ] `PresenceChannel.joining(callback)` -- receive `member_added` events
- [ ] `PresenceChannel.leaving(callback)` -- receive `member_removed` events
- [ ] Implement `leave(channelName)` -- unsubscribe from channel, clean up listeners
- [ ] Implement `disconnect()` -- close WebSocket connection, clean up all state
- [ ] Implement `on(event, callback)` -- global event listener (e.g., `connected`, `disconnected`, `reconnecting`, `error`)
- [ ] Authentication flow: before subscribing to private/presence channel, POST to auth endpoint with channel name and socket ID, include configured headers
- [ ] Cache auth tokens per channel for the lifetime of the connection
- [ ] Handle auth failure: emit `subscription_error` event on the channel, do not retry automatically
- [ ] Bundle as ESM and CJS with TypeScript declarations
- [ ] Minified production build (target: < 10 KB gzipped)
- [ ] Unit tests for connection lifecycle (connect, reconnect, disconnect)
- [ ] Unit tests for channel subscription and event listening
- [ ] Unit tests for authentication flow
- [ ] Unit tests for presence channel member tracking

### PHP Client for Server-to-Server Broadcasting

- [ ] Create `RippleClient` class in `lattice/ripple` package
- [ ] `broadcast(string $channel, string $event, array $data)` -- publish directly to Redis pub/sub
- [ ] `broadcastToMultiple(array $channels, string $event, array $data)` -- publish to multiple channels
- [ ] Support connection via Redis instance from `lattice/cache` or standalone Redis connection string
- [ ] No WebSocket connection needed -- pure Redis pub/sub
- [ ] Unit tests for broadcast message format and Redis publishing

### Echo-Compatible API Surface

- [ ] Ensure `Ripple` class method signatures match `laravel-echo` `Echo` class: `channel()`, `private()`, `presence()`, `leave()`, `disconnect()`
- [ ] Ensure `Channel`, `PrivateChannel`, `PresenceChannel` method signatures match Echo: `listen()`, `stopListening()`, `whisper()`, `here()`, `joining()`, `leaving()`
- [ ] Ensure event data format matches Pusher/Echo conventions (`.event-name` notation support)
- [ ] Document migration path from `laravel-echo` to `lattice-ripple.js`
- [ ] Integration test: run the same client code against both Echo (mock) and Ripple, verify identical behavior

---

## Phase 4 -- CLI TUI

### Interactive TUI (`php lattice ripple`)

- [ ] Create `php lattice ripple` command -- interactive TUI entry point
- [ ] Connect to running Ripple server via admin socket or Redis metadata keys
- [ ] Dashboard view: live-updating stats refreshed every 1 second
  - [ ] Total connected clients (number, with +/- delta per second)
  - [ ] Active channels (count)
  - [ ] Messages per second (inbound and outbound)
  - [ ] Server memory usage (MB)
  - [ ] Server uptime
- [ ] Connected clients list view: scrollable table
  - [ ] Columns: Connection ID, Remote IP, Connected Duration, Channels, Messages Sent, Messages Received
  - [ ] Color-coded by activity (green = active recently, gray = idle)
  - [ ] Arrow key navigation
  - [ ] `/` to search/filter by IP or connection ID
- [ ] Channel list view: scrollable table
  - [ ] Columns: Channel Name, Type (public/private/presence), Subscribers, Messages/sec
  - [ ] Sorted by subscriber count descending
  - [ ] Expand a channel to see its subscriber list
- [ ] Keyboard shortcuts: `q` quit, `r` refresh, `Tab` switch views, `1` dashboard, `2` connections, `3` channels, `/` search, `?` help
- [ ] Color-coded channel types: public = blue, private = yellow, presence = green
- [ ] Compact mode for narrow terminals (< 80 columns)

### Non-Interactive CLI Commands

- [ ] `php lattice ripple:connections` -- print connected clients as a formatted table, exit
  - [ ] Support `--json` flag for machine-readable output
  - [ ] Support `--count` flag for just the count
- [ ] `php lattice ripple:channels` -- print active channels with subscriber counts as a formatted table, exit
  - [ ] Support `--json` flag for machine-readable output
  - [ ] Support `--type=private` filter
- [ ] `php lattice ripple:stats` -- print server stats (connections, channels, messages/sec, memory, uptime), exit
  - [ ] Support `--json` flag for machine-readable output
  - [ ] Support `--watch` flag for continuous output (refresh every N seconds)
- [ ] Unit tests for all non-interactive commands
- [ ] Unit tests for `--json` output format

---

## Phase 5 -- Polish

### Rate Limiting Per Connection

- [ ] Implement token bucket rate limiter per connection
- [ ] Rate limit inbound messages: max N messages per second (configurable, default 100)
- [ ] Rate limit channel subscriptions: max N subscriptions per second (configurable, default 10)
- [ ] Rate limit whispers separately: max N per second (configurable, default 10)
- [ ] On rate limit exceeded: send error message to client, do not close connection (allow recovery)
- [ ] On sustained rate limit violation (e.g., 10 seconds of continuous excess): close connection with 1008 Policy Violation
- [ ] Log rate limit events for monitoring
- [ ] Unit tests for rate limiting with burst and sustained patterns

### Message Size Limits

- [ ] Enforce maximum message size on inbound frames (configurable, default 64 KB)
- [ ] Reject oversized messages: close connection with 1009 Message Too Big
- [ ] Enforce maximum outbound message size: if a broadcast payload exceeds the limit, log a warning and truncate or reject
- [ ] Track message sizes in connection stats
- [ ] Unit tests for message size enforcement

### SSL/TLS Support

- [ ] Implement TLS socket wrapper using `stream_socket_server` with `ssl://` context
- [ ] Accept `ripple.ssl.cert` and `ripple.ssl.key` configuration paths
- [ ] Support certificate chain (intermediate certificates)
- [ ] Support passphrase-protected private keys
- [ ] Auto-detect `wss://` vs `ws://` based on `ripple.ssl.enabled`
- [ ] Print TLS info in server startup banner (certificate CN, expiry date)
- [ ] Unit tests for TLS configuration validation
- [ ] Integration test: connect via `wss://` and exchange messages

### Horizontal Scaling Documentation

- [ ] Document Redis pub/sub architecture for multi-server deployment
- [ ] Document load balancer configuration (sticky sessions NOT required, any connection to any server)
- [ ] Document health check endpoint for load balancers (`GET /health` on the HTTP port)
- [ ] Document Redis memory requirements for pub/sub at scale
- [ ] Document monitoring multiple Ripple instances from a single TUI
- [ ] Provide example Nginx configuration for WebSocket proxy
- [ ] Provide example Docker Compose setup with multiple Ripple instances behind a load balancer

### Tests

- [ ] Unit tests: WebSocket frame parser and serializer (all opcodes, edge cases)
- [ ] Unit tests: Connection manager lifecycle (accept, heartbeat, timeout, close)
- [ ] Unit tests: Channel manager (subscribe, unsubscribe, broadcast routing)
- [ ] Unit tests: Presence channel member tracking (join, leave, multi-device)
- [ ] Unit tests: Auth endpoint (valid/invalid credentials, private/presence channels)
- [ ] Unit tests: Authorization callbacks (closure-based, class-based, wildcard patterns)
- [ ] Unit tests: Rate limiter (token bucket, burst, sustained violation)
- [ ] Unit tests: Configuration validation
- [ ] Unit tests: RippleServiceProvider bindings
- [ ] Integration test: full client lifecycle -- connect, authenticate, subscribe to private channel, receive broadcast, whisper, unsubscribe, disconnect
- [ ] Integration test: presence channel -- join, see members, receive join/leave events
- [ ] Integration test: horizontal scaling -- two server instances, broadcast on one, receive on the other
- [ ] Integration test: graceful shutdown -- server sends close to all clients, clients receive close
- [ ] Integration test: reconnection -- client reconnects after server restart, re-subscribes to channels
- [ ] Performance test: 1000 concurrent connections with sustained message broadcast
- [ ] Performance test: channel with 10,000 subscribers, single broadcast delivery time

### Documentation

- [ ] Installation guide (Composer require, module registration, config publish)
- [ ] Quick start: minimal setup to get a WebSocket server running
- [ ] Channel authorization guide (private and presence channels)
- [ ] Broadcasting events guide (BroadcastEvent interface, `#[Broadcast]` attribute)
- [ ] JavaScript client SDK reference (`lattice-ripple.js`)
- [ ] Migration guide from Laravel Echo / Reverb
- [ ] Configuration reference (all config options with defaults and descriptions)
- [ ] Horizontal scaling guide (Redis pub/sub, multiple instances, load balancer)
- [ ] SSL/TLS setup guide
- [ ] CLI TUI usage guide
- [ ] Troubleshooting guide (common issues: firewall, CORS, origin restrictions)
