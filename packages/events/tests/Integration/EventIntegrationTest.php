<?php

declare(strict_types=1);

namespace Lattice\Events\Tests\Integration;

use Lattice\Events\AsyncEventDispatcher;
use Lattice\Events\Attributes\Listener;
use Lattice\Events\EventDispatcher;
use Lattice\Events\EventSubscriberInterface;
use Lattice\Events\ListenerDiscoverer;
use Lattice\Events\StoppableEvent;
use Lattice\Events\Testing\InMemoryEventCollector;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

// --- Inline test event classes ---

final class UserCreatedEvent
{
    public function __construct(
        public readonly string $email,
        public readonly string $name = 'Test User',
    ) {}
}

final class OrderPlacedEvent
{
    public function __construct(
        public readonly int $orderId,
        public readonly float $total,
    ) {}
}

final class UserRegisteredEvent
{
    public function __construct(
        public readonly string $email,
        public readonly string $name,
    ) {}
}

final class StoppableNotificationEvent extends StoppableEvent
{
    public function __construct(
        public readonly string $message,
    ) {}
}

final class PayloadEvent
{
    public function __construct(
        public readonly array $data,
    ) {}
}

// --- Inline listener classes ---

final class UserCreatedListener
{
    public bool $handled = false;
    public ?UserCreatedEvent $receivedEvent = null;

    #[Listener(event: UserCreatedEvent::class)]
    public function onUserCreated(UserCreatedEvent $event): void
    {
        $this->handled = true;
        $this->receivedEvent = $event;
    }
}

final class OrderListener
{
    public bool $handled = false;

    #[Listener(event: OrderPlacedEvent::class, priority: 10)]
    public function onOrderPlaced(OrderPlacedEvent $event): void
    {
        $this->handled = true;
    }
}

final class MultiEventListener
{
    public bool $userHandled = false;
    public bool $orderHandled = false;

    #[Listener(event: UserCreatedEvent::class)]
    public function onUserCreated(UserCreatedEvent $event): void
    {
        $this->userHandled = true;
    }

    #[Listener(event: OrderPlacedEvent::class)]
    public function onOrderPlaced(OrderPlacedEvent $event): void
    {
        $this->orderHandled = true;
    }
}

final class PriorityListenerA
{
    /** @var list<string> */
    public static array $order = [];

    #[Listener(event: UserCreatedEvent::class, priority: 1)]
    public function handle(UserCreatedEvent $event): void
    {
        self::$order[] = 'A-priority-1';
    }
}

final class PriorityListenerB
{
    #[Listener(event: UserCreatedEvent::class, priority: 10)]
    public function handle(UserCreatedEvent $event): void
    {
        PriorityListenerA::$order[] = 'B-priority-10';
    }
}

final class UserActivitySubscriber implements EventSubscriberInterface
{
    public bool $loginHandled = false;
    public bool $logoutHandled = false;

    public static function getSubscribedEvents(): array
    {
        return [
            'user.login' => 'onLogin',
            'user.logout' => ['onLogout', 5],
        ];
    }

    public function onLogin(object $event): void
    {
        $this->loginHandled = true;
    }

    public function onLogout(object $event): void
    {
        $this->logoutHandled = true;
    }
}

// --- Fake email service for full cycle test ---

final class FakeEmailService
{
    /** @var list<array{to: string, subject: string}> */
    public array $sent = [];

    public function send(string $to, string $subject): void
    {
        $this->sent[] = ['to' => $to, 'subject' => $subject];
    }
}

final class WelcomeEmailListener
{
    public function __construct(
        private readonly FakeEmailService $emailService,
    ) {}

    #[Listener(event: UserRegisteredEvent::class)]
    public function onUserRegistered(UserRegisteredEvent $event): void
    {
        $this->emailService->send($event->email, "Welcome, {$event->name}!");
    }
}

// --- Integration Test ---

final class EventIntegrationTest extends TestCase
{
    private EventDispatcher $dispatcher;

    protected function setUp(): void
    {
        $this->dispatcher = new EventDispatcher();
        PriorityListenerA::$order = [];
    }

    // 1. Basic dispatch
    #[Test]
    public function test_basic_dispatch_executes_registered_listener(): void
    {
        $called = false;
        $receivedEvent = null;

        $this->dispatcher->listen(UserCreatedEvent::class, function (UserCreatedEvent $event) use (&$called, &$receivedEvent) {
            $called = true;
            $receivedEvent = $event;
        });

        $event = new UserCreatedEvent(email: 'john@example.com');
        $result = $this->dispatcher->dispatch($event);

        $this->assertTrue($called, 'Listener callback should have been invoked');
        $this->assertSame($event, $result, 'Dispatch should return the same event object');
        $this->assertSame($event, $receivedEvent, 'Listener should receive the dispatched event');
    }

    // 2. #[Listener] attribute auto-discovery
    #[Test]
    public function test_listener_attribute_auto_discovered_and_executed(): void
    {
        $listener = new UserCreatedListener();
        $discoverer = new ListenerDiscoverer($this->dispatcher);

        $count = $discoverer->discover($listener);

        $this->assertSame(1, $count, 'Should discover exactly 1 listener');

        $event = new UserCreatedEvent(email: 'auto@example.com');
        $this->dispatcher->dispatch($event);

        $this->assertTrue($listener->handled, 'Listener method should have been called');
        $this->assertSame($event, $listener->receivedEvent);
    }

    // 3. Multiple listeners on the same event
    #[Test]
    public function test_multiple_listeners_on_same_event_both_execute(): void
    {
        $firstCalled = false;
        $secondCalled = false;

        $this->dispatcher->listen(UserCreatedEvent::class, function () use (&$firstCalled) {
            $firstCalled = true;
        });

        $this->dispatcher->listen(UserCreatedEvent::class, function () use (&$secondCalled) {
            $secondCalled = true;
        });

        $this->dispatcher->dispatch(new UserCreatedEvent(email: 'multi@example.com'));

        $this->assertTrue($firstCalled, 'First listener should execute');
        $this->assertTrue($secondCalled, 'Second listener should execute');
    }

    // 4. Priority ordering
    #[Test]
    public function test_priority_ordering_higher_runs_first(): void
    {
        $order = [];

        $this->dispatcher->listen(UserCreatedEvent::class, function () use (&$order) {
            $order[] = 'priority-1';
        }, 1);

        $this->dispatcher->listen(UserCreatedEvent::class, function () use (&$order) {
            $order[] = 'priority-10';
        }, 10);

        $this->dispatcher->listen(UserCreatedEvent::class, function () use (&$order) {
            $order[] = 'priority-5';
        }, 5);

        $this->dispatcher->dispatch(new UserCreatedEvent(email: 'priority@example.com'));

        $this->assertSame(['priority-10', 'priority-5', 'priority-1'], $order);
    }

    // 5. StoppableEvent - first listener stops propagation, second not called
    #[Test]
    public function test_stoppable_event_stops_propagation(): void
    {
        $firstCalled = false;
        $secondCalled = false;

        $this->dispatcher->listen(StoppableNotificationEvent::class, function (StoppableNotificationEvent $event) use (&$firstCalled) {
            $firstCalled = true;
            $event->stopPropagation();
        }, 10);

        $this->dispatcher->listen(StoppableNotificationEvent::class, function () use (&$secondCalled) {
            $secondCalled = true;
        }, 1);

        $event = new StoppableNotificationEvent(message: 'halt');
        $this->dispatcher->dispatch($event);

        $this->assertTrue($firstCalled, 'First listener should be called');
        $this->assertFalse($secondCalled, 'Second listener should NOT be called after propagation stopped');
        $this->assertTrue($event->isPropagationStopped());
    }

    // 6. Event subscriber - all methods registered
    #[Test]
    public function test_event_subscriber_registers_all_methods(): void
    {
        $subscriber = new UserActivitySubscriber();
        $this->dispatcher->subscribe($subscriber);

        $this->dispatcher->dispatch(new \stdClass(), 'user.login');
        $this->assertTrue($subscriber->loginHandled, 'Login handler should fire');

        $this->dispatcher->dispatch(new \stdClass(), 'user.logout');
        $this->assertTrue($subscriber->logoutHandled, 'Logout handler should fire');
    }

    // 7. AsyncEventDispatcher - events queued then flushed
    #[Test]
    public function test_async_dispatcher_queues_and_flushes(): void
    {
        $callCount = 0;

        $this->dispatcher->listen(UserCreatedEvent::class, function () use (&$callCount) {
            $callCount++;
        });

        $async = new AsyncEventDispatcher($this->dispatcher);

        $event1 = new UserCreatedEvent(email: 'async1@example.com');
        $event2 = new UserCreatedEvent(email: 'async2@example.com');

        $async->dispatch($event1);
        $async->dispatch($event2);

        $this->assertSame(0, $callCount, 'No listeners should fire before flush');

        $async->flush();

        $this->assertSame(2, $callCount, 'Both events should fire after flush');
    }

    // 8. InMemoryEventCollector - assertDispatched and assertNotDispatched
    #[Test]
    public function test_in_memory_event_collector_assert_dispatched(): void
    {
        $collector = new InMemoryEventCollector();

        $event = new UserCreatedEvent(email: 'fake@example.com');
        $collector->dispatch($event);

        $collector->assertDispatched(UserCreatedEvent::class);
        $collector->assertNotDispatched(OrderPlacedEvent::class);

        $this->assertCount(1, $collector->getDispatched());
        $this->assertSame($event, $collector->getDispatched()[0]);
    }

    // 9. Payload preservation - event data reaches listener intact
    #[Test]
    public function test_payload_preservation_through_dispatch(): void
    {
        $receivedData = null;

        $this->dispatcher->listen(PayloadEvent::class, function (PayloadEvent $event) use (&$receivedData) {
            $receivedData = $event->data;
        });

        $payload = ['key' => 'value', 'nested' => ['a' => 1, 'b' => 2], 'count' => 42];
        $this->dispatcher->dispatch(new PayloadEvent(data: $payload));

        $this->assertSame($payload, $receivedData, 'Listener should receive exact same data as dispatched');
    }

    // 10. ListenerDiscoverer::discover() finds all #[Listener] methods on object
    #[Test]
    public function test_listener_discoverer_finds_all_listener_methods(): void
    {
        $multiListener = new MultiEventListener();
        $discoverer = new ListenerDiscoverer($this->dispatcher);

        $count = $discoverer->discover($multiListener);

        $this->assertSame(2, $count, 'Should discover 2 listener methods');

        $this->dispatcher->dispatch(new UserCreatedEvent(email: 'disc@example.com'));
        $this->assertTrue($multiListener->userHandled);

        $this->dispatcher->dispatch(new OrderPlacedEvent(orderId: 1, total: 99.99));
        $this->assertTrue($multiListener->orderHandled);
    }

    // 11. discoverAll() finds listeners from multiple objects
    #[Test]
    public function test_discover_all_finds_listeners_from_multiple_objects(): void
    {
        $userListener = new UserCreatedListener();
        $orderListener = new OrderListener();
        $discoverer = new ListenerDiscoverer($this->dispatcher);

        $total = $discoverer->discoverAll([$userListener, $orderListener]);

        $this->assertSame(2, $total, 'Should discover 1 listener from each object');

        $this->dispatcher->dispatch(new UserCreatedEvent(email: 'all@example.com'));
        $this->assertTrue($userListener->handled);

        $this->dispatcher->dispatch(new OrderPlacedEvent(orderId: 5, total: 50.0));
        $this->assertTrue($orderListener->handled);
    }

    // 12. Full cycle: UserRegistered -> welcome email via fake service
    #[Test]
    public function test_full_cycle_user_registered_sends_welcome_email(): void
    {
        $emailService = new FakeEmailService();
        $welcomeListener = new WelcomeEmailListener($emailService);

        $discoverer = new ListenerDiscoverer($this->dispatcher);
        $discoverer->discover($welcomeListener);

        $event = new UserRegisteredEvent(
            email: 'newuser@example.com',
            name: 'Jane Doe',
        );

        $result = $this->dispatcher->dispatch($event);

        $this->assertSame($event, $result);
        $this->assertCount(1, $emailService->sent, 'Exactly one email should be sent');
        $this->assertSame('newuser@example.com', $emailService->sent[0]['to']);
        $this->assertSame('Welcome, Jane Doe!', $emailService->sent[0]['subject']);
    }

    // Bonus: Priority ordering via discovered #[Listener] attributes
    #[Test]
    public function test_discovered_listener_priority_ordering(): void
    {
        $listenerA = new PriorityListenerA();
        $listenerB = new PriorityListenerB();

        $discoverer = new ListenerDiscoverer($this->dispatcher);
        $discoverer->discoverAll([$listenerA, $listenerB]);

        $this->dispatcher->dispatch(new UserCreatedEvent(email: 'prio@example.com'));

        $this->assertSame(
            ['B-priority-10', 'A-priority-1'],
            PriorityListenerA::$order,
            'Priority 10 listener should run before priority 1',
        );
    }

    // Bonus: Async flush clears queue - second flush is a no-op
    #[Test]
    public function test_async_flush_clears_queue(): void
    {
        $callCount = 0;

        $this->dispatcher->listen(UserCreatedEvent::class, function () use (&$callCount) {
            $callCount++;
        });

        $async = new AsyncEventDispatcher($this->dispatcher);
        $async->dispatch(new UserCreatedEvent(email: 'once@example.com'));

        $async->flush();
        $this->assertSame(1, $callCount);

        $async->flush();
        $this->assertSame(1, $callCount, 'Second flush should not re-dispatch events');
    }
}
