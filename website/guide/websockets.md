---
outline: deep
---

# WebSockets

LatticePHP provides real-time WebSocket support through the `lattice/ripple` package. Broadcast events to channels, authenticate private channels, and track presence -- all with PHP attributes.

## Channel Types

| Type | Class | Access | Use Case |
|---|---|---|---|
| Public | `Channel` | Anyone can subscribe | Public notifications, status updates |
| Private | `PrivateChannel` | Authenticated users only | User-specific events |
| Presence | `PresenceChannel` | Authenticated + tracked | Who's online, collaborative editing |

## Broadcasting Events

Use `#[BroadcastAttribute]` to broadcast events to channels:

```php
use Lattice\Ripple\Broadcasting\BroadcastAttribute;

#[BroadcastAttribute('orders.{orderId}', as: 'order.updated')]
final class OrderUpdated
{
    public function __construct(
        public readonly int $orderId,
        public readonly string $status,
        public readonly float $total,
    ) {}
}
```

When dispatched, this event is broadcast to the `orders.42` channel (where `{orderId}` is interpolated from the event property).

::: tip
Property interpolation in channel names is resolved at broadcast time. `orders.{orderId}` becomes `orders.42` when `$event->orderId` is `42`.
:::

## Publishing from Services

Dispatch broadcastable events through the event dispatcher:

```php
final class OrderService
{
    public function __construct(
        private readonly EventDispatcher $events,
    ) {}

    public function updateStatus(int $orderId, string $status): void
    {
        $order = Order::findOrFail($orderId);
        $order->update(['status' => $status]);

        $this->events->dispatch(new OrderUpdated(
            orderId: $orderId,
            status: $status,
            total: $order->total,
        ));
    }
}
```

## Channel Authentication

Private and presence channels require authentication. The `ChannelAuthenticator` verifies that the user has access:

```php
use Lattice\Ripple\ChannelManager;

$channels = new ChannelManager();

// Register a private channel with an auth callback
$channels->private('orders.{orderId}', function (Principal $user, int $orderId) {
    $order = Order::find($orderId);
    return $order && (int) $user->getId() === $order->user_id;
});

// Register a presence channel
$channels->presence('workspace.{workspaceId}', function (Principal $user, int $workspaceId) {
    return $user->hasWorkspace($workspaceId);
});
```

## Starting the Server

```bash
# Start the WebSocket server
php bin/lattice ripple:serve

# List active channels
php bin/lattice ripple:channels

# Show active connections
php bin/lattice ripple:connections
```

## Client-Side Connection

Connect from JavaScript:

```javascript
const ws = new WebSocket('ws://localhost:8080');

ws.onopen = () => {
    ws.send(JSON.stringify({
        event: 'subscribe',
        channel: 'orders.42',
        token: 'Bearer eyJhbG...',  // JWT for private channels
    }));
};

ws.onmessage = (event) => {
    const data = JSON.parse(event.data);
    console.log(data.event, data.payload);
    // "order.updated" { orderId: 42, status: "shipped", total: 99.99 }
};
```

## Broadcast Drivers

| Driver | Use Case |
|---|---|
| `RedisBroadcastDriver` | Production -- pub/sub across multiple workers |
| `LogBroadcastDriver` | Development -- logs broadcasts to the log channel |
| `NullBroadcastDriver` | Testing -- discards all broadcasts |
| `FakeBroadcaster` | Testing -- captures broadcasts for assertions |

## Testing

```php
use Lattice\Events\Testing\FakeBroadcaster;

$broadcaster = new FakeBroadcaster();
$container->instance(BroadcastDriverInterface::class, $broadcaster);

// ... trigger an action that broadcasts ...

$broadcaster->assertBroadcast('orders.42', 'order.updated');
```

## Next Steps

- [Events & Listeners](events.md) -- event system fundamentals
- [Microservices](microservices.md) -- message-based communication
- [Authentication](auth.md) -- JWT for channel authentication
