<?php

declare(strict_types=1);

namespace Lattice\Events\Tests\Unit;

use Lattice\Events\Attributes\Listener;
use Lattice\Events\EventDispatcher;
use Lattice\Events\ListenerDiscoverer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ListenerAttributeTest extends TestCase
{
    #[Test]
    public function test_listener_attribute_stores_event_name(): void
    {
        $attr = new Listener('App\Events\UserCreated');

        self::assertSame('App\Events\UserCreated', $attr->event);
        self::assertSame(0, $attr->priority);
    }

    #[Test]
    public function test_listener_attribute_stores_priority(): void
    {
        $attr = new Listener('App\Events\UserCreated', priority: 10);

        self::assertSame('App\Events\UserCreated', $attr->event);
        self::assertSame(10, $attr->priority);
    }

    #[Test]
    public function test_listener_discoverer_auto_registers_listeners(): void
    {
        $dispatcher = new EventDispatcher();
        $discoverer = new ListenerDiscoverer($dispatcher);

        $service = new TestListenerService();
        $count = $discoverer->discover($service);

        self::assertSame(2, $count);
        self::assertTrue($dispatcher->hasListeners(UserCreatedEvent::class));
        self::assertTrue($dispatcher->hasListeners(OrderPlacedEvent::class));
    }

    #[Test]
    public function test_discovered_listener_receives_event(): void
    {
        $dispatcher = new EventDispatcher();
        $discoverer = new ListenerDiscoverer($dispatcher);

        $service = new TestListenerService();
        $discoverer->discover($service);

        $event = new UserCreatedEvent('john@example.com');
        $dispatcher->dispatch($event);

        self::assertSame('john@example.com', $service->lastUserEmail);
    }

    #[Test]
    public function test_discoverer_handles_class_with_no_listeners(): void
    {
        $dispatcher = new EventDispatcher();
        $discoverer = new ListenerDiscoverer($dispatcher);

        $service = new NoListenerService();
        $count = $discoverer->discover($service);

        self::assertSame(0, $count);
    }

    #[Test]
    public function test_discover_all_registers_from_multiple_objects(): void
    {
        $dispatcher = new EventDispatcher();
        $discoverer = new ListenerDiscoverer($dispatcher);

        $service1 = new TestListenerService();
        $service2 = new AnotherListenerService();

        $count = $discoverer->discoverAll([$service1, $service2]);

        self::assertSame(3, $count); // 2 from service1, 1 from service2
        self::assertTrue($dispatcher->hasListeners(UserCreatedEvent::class));
        self::assertTrue($dispatcher->hasListeners(OrderPlacedEvent::class));
        self::assertTrue($dispatcher->hasListeners(PaymentReceivedEvent::class));
    }

    #[Test]
    public function test_listener_priority_is_respected(): void
    {
        $dispatcher = new EventDispatcher();
        $discoverer = new ListenerDiscoverer($dispatcher);

        $service = new PriorityListenerService();
        $discoverer->discover($service);

        $event = new UserCreatedEvent('test@test.com');
        $dispatcher->dispatch($event);

        // Higher priority (10) runs first, so 'high' should come before 'low'
        self::assertSame(['high', 'low'], $service->callOrder);
    }
}

final class UserCreatedEvent
{
    public function __construct(public readonly string $email) {}
}

final class OrderPlacedEvent
{
    public function __construct(public readonly string $orderId = 'ORD-1') {}
}

final class PaymentReceivedEvent
{
    public function __construct(public readonly float $amount = 0.0) {}
}

final class TestListenerService
{
    public ?string $lastUserEmail = null;

    #[Listener(UserCreatedEvent::class)]
    public function onUserCreated(UserCreatedEvent $event): void
    {
        $this->lastUserEmail = $event->email;
    }

    #[Listener(OrderPlacedEvent::class)]
    public function onOrderPlaced(OrderPlacedEvent $event): void
    {
        // no-op
    }
}

final class AnotherListenerService
{
    #[Listener(PaymentReceivedEvent::class)]
    public function onPayment(PaymentReceivedEvent $event): void
    {
        // no-op
    }
}

final class NoListenerService
{
    public function regularMethod(): void
    {
        // no listener attribute
    }
}

final class PriorityListenerService
{
    /** @var list<string> */
    public array $callOrder = [];

    #[Listener(UserCreatedEvent::class, priority: 0)]
    public function lowPriority(UserCreatedEvent $event): void
    {
        $this->callOrder[] = 'low';
    }

    #[Listener(UserCreatedEvent::class, priority: 10)]
    public function highPriority(UserCreatedEvent $event): void
    {
        $this->callOrder[] = 'high';
    }
}
