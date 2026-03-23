---
outline: deep
---

# Events & Listeners

LatticePHP provides an event system for decoupling components. Dispatch events from your services, and listeners handle them -- synchronously or asynchronously.

## Defining Events

Events are plain PHP classes:

```php
<?php
declare(strict_types=1);

namespace App\Events;

final readonly class ContactCreated
{
    public function __construct(
        public int $contactId,
        public string $email,
        public string $createdBy,
    ) {}
}
```

## Defining Listeners

Annotate a class method with `#[Listener]` to subscribe it to an event:

```php
<?php
declare(strict_types=1);

namespace App\Listeners;

use App\Events\ContactCreated;
use Lattice\Events\Attributes\Listener;

final class SendWelcomeEmail
{
    public function __construct(
        private readonly MailManager $mail,
    ) {}

    #[Listener(event: ContactCreated::class)]
    public function handle(ContactCreated $event): void
    {
        $this->mail->to($event->email)->send('welcome', [
            'contactId' => $event->contactId,
        ]);
    }
}
```

::: tip
No `EventServiceProvider` needed. The `#[Listener]` attribute auto-registers the listener at boot time through the compiler.
:::

## Dispatching Events

Use the `EventDispatcher` to fire events:

```php
final class ContactService
{
    public function __construct(
        private readonly EventDispatcher $events,
    ) {}

    public function create(CreateContactDto $dto, Principal $user): Contact
    {
        $contact = Contact::create([...]);

        $this->events->dispatch(new ContactCreated(
            contactId: $contact->id,
            email: $contact->email,
            createdBy: $user->getId(),
        ));

        return $contact;
    }
}
```

## Async Events

Dispatch events to a queue for asynchronous processing:

```php
use Lattice\Events\AsyncEventDispatcher;

final class OrderService
{
    public function __construct(
        private readonly AsyncEventDispatcher $events,
    ) {}

    public function complete(int $orderId): void
    {
        // This listener runs in a queue worker, not the current request
        $this->events->dispatch(new OrderCompleted(orderId: $orderId));
    }
}
```

## Event Subscribers

For classes that listen to multiple events, implement `EventSubscriberInterface`:

```php
final class AuditSubscriber implements EventSubscriberInterface
{
    public function getSubscribedEvents(): array
    {
        return [
            ContactCreated::class => 'onContactCreated',
            ContactDeleted::class => 'onContactDeleted',
            DealClosed::class => 'onDealClosed',
        ];
    }

    public function onContactCreated(ContactCreated $event): void { /* ... */ }
    public function onContactDeleted(ContactDeleted $event): void { /* ... */ }
    public function onDealClosed(DealClosed $event): void { /* ... */ }
}
```

## Stoppable Events

Extend `StoppableEvent` to allow listeners to halt propagation:

```php
final class OrderValidation extends StoppableEvent
{
    public bool $approved = true;
    public string $reason = '';
}

// In a listener:
$event->approved = false;
$event->reason = 'Insufficient inventory';
$event->stopPropagation(); // No further listeners will run
```

## Broadcasting

Broadcast events to WebSocket channels in real-time using the `ShouldBroadcast` interface:

```php
final class DealStageChanged implements ShouldBroadcast
{
    public function __construct(
        public readonly int $dealId,
        public readonly string $newStage,
    ) {}

    public function broadcastOn(): string
    {
        return 'deals.' . $this->dealId;
    }
}
```

Broadcast drivers: Redis (production), Log (development), Null (testing).

See [WebSockets](websockets.md) for the full broadcasting guide.

## Testing Events

Use `FakeEventBus` to capture and assert dispatched events:

```php
use Lattice\Testing\Fakes\FakeEventBus;

public function test_create_contact_fires_event(): void
{
    $events = new FakeEventBus();
    $this->app->getContainer()->instance(EventBusInterface::class, $events);

    $this->postJson('/api/contacts', [
        'name' => 'Alice',
        'email' => 'alice@test.com',
    ]);

    $events->assertDispatched(ContactCreated::class);
    $events->assertNotDispatched(ContactDeleted::class);
}
```

Use `InMemoryEventCollector` to inspect all dispatched events:

```php
$collector = new InMemoryEventCollector();
// ... run code ...
$all = $collector->getEvents(); // list of all dispatched events
```

## Next Steps

- [Queues & Jobs](queues.md) -- async job processing
- [WebSockets](websockets.md) -- real-time event broadcasting
- [Testing](testing.md) -- faking events in tests
