# Microservices

LatticePHP provides first-class support for building microservices with typed message controllers, multiple transport adapters, and the same guard/pipe/interceptor pipeline used by HTTP controllers.

---

## Message Controllers

Message controllers handle incoming messages from transport layers. They are annotated with `#[MessageController]` and their methods with `#[CommandPattern]`, `#[EventPattern]`, or `#[ReplyPattern]`.

```php
<?php

declare(strict_types=1);

namespace App\Modules\Orders;

use Lattice\Microservices\Attributes\MessageController;
use Lattice\Microservices\Attributes\CommandPattern;
use Lattice\Microservices\Attributes\EventPattern;
use Lattice\Microservices\Attributes\ReplyPattern;
use Lattice\Microservices\MessageEnvelope;

#[MessageController(transport: 'nats')]
final class OrderMessageController
{
    public function __construct(
        private readonly OrderService $orders,
    ) {}

    #[CommandPattern(pattern: 'orders.create')]
    public function createOrder(MessageEnvelope $envelope): array
    {
        $payload = $envelope->getPayload();
        $order = $this->orders->create($payload);

        return ['orderId' => $order->id, 'status' => 'created'];
    }

    #[EventPattern(pattern: 'payments.completed')]
    public function onPaymentCompleted(MessageEnvelope $envelope): void
    {
        $payload = $envelope->getPayload();
        $this->orders->markPaid($payload['orderId']);
    }

    #[ReplyPattern(pattern: 'orders.status')]
    public function getOrderStatus(MessageEnvelope $envelope): array
    {
        $order = $this->orders->find($envelope->getPayload()['orderId']);
        return ['orderId' => $order->id, 'status' => $order->status];
    }
}
```

### Pattern Types

| Attribute | Namespace | Purpose |
|---|---|---|
| `#[CommandPattern(pattern: 'orders.create')]` | `Lattice\Microservices\Attributes` | Request-response: caller expects a reply |
| `#[EventPattern(pattern: 'payments.completed')]` | `Lattice\Microservices\Attributes` | Fire-and-forget: no reply expected |
| `#[ReplyPattern(pattern: 'orders.status')]` | `Lattice\Microservices\Attributes` | Query pattern: read-only response |

---

## MessageEnvelope

Every message is wrapped in a `MessageEnvelope` (`Lattice\Microservices\MessageEnvelope`).

```php
use Lattice\Microservices\MessageEnvelope;

$envelope = new MessageEnvelope(
    messageId: 'msg_abc123',
    messageType: 'orders.create',
    payload: ['productId' => 42, 'quantity' => 2],
    schemaVersion: '1.0.0',
    correlationId: 'corr_xyz',
    causationId: 'cause_789',
    headers: ['X-Tenant-Id' => 'tenant_1'],
);

$envelope->getMessageId();       // 'msg_abc123'
$envelope->getMessageType();     // 'orders.create'
$envelope->getPayload();         // ['productId' => 42, 'quantity' => 2]
$envelope->getSchemaVersion();   // '1.0.0'
$envelope->getCorrelationId();   // 'corr_xyz' (auto-generated if not provided)
$envelope->getCausationId();     // 'cause_789'
$envelope->getHeaders();         // ['X-Tenant-Id' => 'tenant_1']
$envelope->getTimestamp();       // DateTimeImmutable
$envelope->getAttempt();         // 1
```

The `correlationId` is auto-generated if not provided, ensuring every message is traceable.

---

## MessageRouter

The `MessageRouter` (`Lattice\Microservices\MessageRouter`) dispatches envelopes to the correct handler.

```php
use Lattice\Microservices\MessageRouter;
use Lattice\Microservices\MessageEnvelope;

$router = new MessageRouter();
$router->register('orders.create', OrderMessageController::class, 'createOrder');
$router->register('payments.completed', OrderMessageController::class, 'onPaymentCompleted');
$router->register('orders.*', OrderMessageController::class, 'catchAll');

$match = $router->route($envelope);
// Returns HandlerMatch with: handlerClass, method, matchedPattern
```

The router supports exact matching (priority) and wildcard patterns: `*` matches a single segment, `**` matches multiple segments including dots.

---

## Schema Versioning

Use `SchemaVersion` (`Lattice\Microservices\Versioning\SchemaVersion`) for message compatibility checks.

```php
use Lattice\Microservices\Versioning\SchemaVersion;

$v1 = SchemaVersion::parse('1.2.3');
$v2 = SchemaVersion::parse('1.5.0');
$v3 = SchemaVersion::parse('2.0.0');

$v1->isCompatible($v2); // true (same major version)
$v1->isCompatible($v3); // false (different major version)

echo $v1; // "1.2.3"
```

---

## Transport Adapters

LatticePHP provides transport adapters for common message brokers. All implement `TransportInterface` from `Lattice\Contracts\Messaging`.

### Available Transports

| Package | Transport | Best For |
|---|---|---|
| `lattice/transport-nats` | NATS | Low-latency pub/sub |
| `lattice/transport-rabbitmq` | RabbitMQ | Reliable message queuing |
| `lattice/transport-sqs` | Amazon SQS | AWS-native serverless |
| `lattice/transport-kafka` | Apache Kafka | High-throughput event streaming |

### TransportInterface

Every transport implements:

```php
interface TransportInterface
{
    public function publish(MessageEnvelopeInterface $envelope, string $channel): void;
    public function subscribe(string $channel, callable $handler): void;
    public function acknowledge(MessageEnvelopeInterface $envelope): void;
    public function reject(MessageEnvelopeInterface $envelope, bool $requeue = false): void;
}
```

### Publishing Messages

```php
use Lattice\Microservices\MessageEnvelope;

$envelope = new MessageEnvelope(
    messageId: bin2hex(random_bytes(16)),
    messageType: 'orders.created',
    payload: ['orderId' => 123, 'total' => 49.99],
);

$transport->publish($envelope, 'orders');
```

### Subscribing to Messages

```php
$transport->subscribe('orders', function (MessageEnvelopeInterface $envelope) use ($transport): void {
    try {
        // Process message
        $this->handleOrder($envelope->getPayload());
        $transport->acknowledge($envelope);
    } catch (\Throwable $e) {
        $transport->reject($envelope, requeue: true);
    }
});
```

---

## InMemoryTransport for Testing

The `InMemoryTransport` (`Lattice\Microservices\Transport\InMemoryTransport`) captures all published messages and delivers them synchronously to subscribers.

```php
use Lattice\Microservices\Transport\InMemoryTransport;
use Lattice\Microservices\MessageEnvelope;

$transport = new InMemoryTransport();
$received = [];

$transport->subscribe('orders', function ($envelope) use (&$received): void {
    $received[] = $envelope;
});

$envelope = new MessageEnvelope(
    messageId: 'test-1',
    messageType: 'orders.create',
    payload: ['item' => 'widget'],
);

$transport->publish($envelope, 'orders');

// Assertions
assert(count($received) === 1);
assert($transport->getPublished()[0]['channel'] === 'orders');

// Acknowledge / reject tracking
$transport->acknowledge($envelope);
assert(in_array('test-1', $transport->getAcknowledged()));
```

### InMemoryTransport Inspection Methods

| Method | Returns |
|---|---|
| `getPublished()` | All published `[channel, envelope]` pairs |
| `getSubscriptions()` | All registered subscriptions by channel |
| `getAcknowledged()` | List of acknowledged message IDs |
| `getRejected()` | List of `[messageId, requeue]` pairs |

---

## Module Registration

Register your message controller in a module:

```php
use Lattice\Module\Attribute\Module;

#[Module(
    controllers: [OrderMessageController::class],
    providers: [OrderService::class],
)]
final class OrdersModule {}
```

The compiler discovers `#[MessageController]` attributes and registers routes in the `MessageRouter` automatically.

---

## Error Handling

Failed messages are rejected with an optional requeue flag. For dead-letter handling, configure your transport broker (RabbitMQ DLX, SQS DLQ, Kafka error topics) at the infrastructure level. The `attempt` field on `MessageEnvelope` tracks retry count so handlers can implement max-retry logic.
