<?php

declare(strict_types=1);

namespace Lattice\Events\Tests;

use Lattice\Events\EventDispatcher;
use Lattice\Events\EventSubscriberInterface;
use Lattice\Events\StoppableEvent;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class EventDispatcherTest extends TestCase
{
    private EventDispatcher $dispatcher;

    protected function setUp(): void
    {
        $this->dispatcher = new EventDispatcher();
    }

    #[Test]
    public function it_dispatches_event_to_registered_listener(): void
    {
        $called = false;
        $this->dispatcher->listen('user.created', function (object $event) use (&$called) {
            $called = true;
        });

        $event = new \stdClass();
        $result = $this->dispatcher->dispatch($event, 'user.created');

        $this->assertTrue($called);
        $this->assertSame($event, $result);
    }

    #[Test]
    public function it_dispatches_using_class_name_when_no_event_name_given(): void
    {
        $received = null;
        $event = new class {
            public string $name = 'test';
        };

        $this->dispatcher->listen($event::class, function (object $e) use (&$received) {
            $received = $e;
        });

        $result = $this->dispatcher->dispatch($event);

        $this->assertSame($event, $received);
        $this->assertSame($event, $result);
    }

    #[Test]
    public function it_executes_listeners_sorted_by_priority_descending(): void
    {
        $order = [];

        $this->dispatcher->listen('app.boot', function () use (&$order) {
            $order[] = 'low';
        }, 0);

        $this->dispatcher->listen('app.boot', function () use (&$order) {
            $order[] = 'high';
        }, 10);

        $this->dispatcher->listen('app.boot', function () use (&$order) {
            $order[] = 'medium';
        }, 5);

        $this->dispatcher->dispatch(new \stdClass(), 'app.boot');

        $this->assertSame(['high', 'medium', 'low'], $order);
    }

    #[Test]
    public function it_reports_has_listeners(): void
    {
        $this->assertFalse($this->dispatcher->hasListeners('foo'));

        $this->dispatcher->listen('foo', function () {});

        $this->assertTrue($this->dispatcher->hasListeners('foo'));
    }

    #[Test]
    public function it_forgets_all_listeners_for_event(): void
    {
        $this->dispatcher->listen('foo', function () {});
        $this->dispatcher->listen('foo', function () {});
        $this->assertTrue($this->dispatcher->hasListeners('foo'));

        $this->dispatcher->forget('foo');

        $this->assertFalse($this->dispatcher->hasListeners('foo'));
    }

    #[Test]
    public function it_stops_propagation_for_stoppable_events(): void
    {
        $order = [];

        $this->dispatcher->listen('stop.test', function (StoppableEvent $event) use (&$order) {
            $order[] = 'first';
            $event->stopPropagation();
        }, 10);

        $this->dispatcher->listen('stop.test', function () use (&$order) {
            $order[] = 'second';
        }, 0);

        $event = new class extends StoppableEvent {};
        $this->dispatcher->dispatch($event, 'stop.test');

        $this->assertSame(['first'], $order);
        $this->assertTrue($event->isPropagationStopped());
    }

    #[Test]
    public function it_registers_subscriber(): void
    {
        $subscriber = new class implements EventSubscriberInterface {
            public bool $onCreateCalled = false;
            public bool $onDeleteCalled = false;

            public static function getSubscribedEvents(): array
            {
                return [
                    'user.created' => 'onUserCreated',
                    'user.deleted' => ['onUserDeleted', 5],
                ];
            }

            public function onUserCreated(object $event): void
            {
                $this->onCreateCalled = true;
            }

            public function onUserDeleted(object $event): void
            {
                $this->onDeleteCalled = true;
            }
        };

        $this->dispatcher->subscribe($subscriber);

        $this->dispatcher->dispatch(new \stdClass(), 'user.created');
        $this->assertTrue($subscriber->onCreateCalled);

        $this->dispatcher->dispatch(new \stdClass(), 'user.deleted');
        $this->assertTrue($subscriber->onDeleteCalled);
    }

    #[Test]
    public function it_returns_event_when_no_listeners(): void
    {
        $event = new \stdClass();
        $result = $this->dispatcher->dispatch($event, 'nonexistent');

        $this->assertSame($event, $result);
    }
}
