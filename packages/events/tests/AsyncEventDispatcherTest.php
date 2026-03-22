<?php

declare(strict_types=1);

namespace Lattice\Events\Tests;

use Lattice\Events\AsyncEventDispatcher;
use Lattice\Events\EventDispatcher;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AsyncEventDispatcherTest extends TestCase
{
    #[Test]
    public function it_queues_events_without_dispatching_immediately(): void
    {
        $called = false;
        $dispatcher = new EventDispatcher();
        $dispatcher->listen('order.placed', function () use (&$called) {
            $called = true;
        });

        $async = new AsyncEventDispatcher($dispatcher);
        $async->dispatch(new \stdClass(), 'order.placed');

        $this->assertFalse($called, 'Event should not be dispatched immediately');
    }

    #[Test]
    public function it_dispatches_all_queued_events_on_flush(): void
    {
        $log = [];
        $dispatcher = new EventDispatcher();
        $dispatcher->listen('a', function () use (&$log) { $log[] = 'a'; });
        $dispatcher->listen('b', function () use (&$log) { $log[] = 'b'; });

        $async = new AsyncEventDispatcher($dispatcher);
        $async->dispatch(new \stdClass(), 'a');
        $async->dispatch(new \stdClass(), 'b');

        $this->assertEmpty($log);

        $async->flush();

        $this->assertSame(['a', 'b'], $log);
    }

    #[Test]
    public function it_clears_queue_after_flush(): void
    {
        $count = 0;
        $dispatcher = new EventDispatcher();
        $dispatcher->listen('x', function () use (&$count) { $count++; });

        $async = new AsyncEventDispatcher($dispatcher);
        $async->dispatch(new \stdClass(), 'x');
        $async->flush();
        $async->flush(); // second flush should be no-op

        $this->assertSame(1, $count);
    }

    #[Test]
    public function it_returns_event_from_dispatch(): void
    {
        $dispatcher = new EventDispatcher();
        $async = new AsyncEventDispatcher($dispatcher);

        $event = new \stdClass();
        $result = $async->dispatch($event, 'test');

        $this->assertSame($event, $result);
    }
}
