<?php

declare(strict_types=1);

namespace Lattice\RoadRunner\Tests;

use Lattice\RoadRunner\WorkerLifecycle;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class WorkerLifecycleTest extends TestCase
{
    #[Test]
    public function it_calls_startup_callbacks(): void
    {
        $lifecycle = new WorkerLifecycle();
        $called = false;

        $lifecycle->onStartup(function () use (&$called): void {
            $called = true;
        });

        $lifecycle->triggerStartup();

        $this->assertTrue($called);
    }

    #[Test]
    public function it_calls_multiple_startup_callbacks_in_order(): void
    {
        $lifecycle = new WorkerLifecycle();
        $order = [];

        $lifecycle->onStartup(function () use (&$order): void {
            $order[] = 'first';
        });
        $lifecycle->onStartup(function () use (&$order): void {
            $order[] = 'second';
        });

        $lifecycle->triggerStartup();

        $this->assertSame(['first', 'second'], $order);
    }

    #[Test]
    public function it_calls_shutdown_callbacks(): void
    {
        $lifecycle = new WorkerLifecycle();
        $called = false;

        $lifecycle->onShutdown(function () use (&$called): void {
            $called = true;
        });

        $lifecycle->triggerShutdown();

        $this->assertTrue($called);
    }

    #[Test]
    public function it_calls_request_callbacks(): void
    {
        $lifecycle = new WorkerLifecycle();
        $count = 0;

        $lifecycle->onRequest(function () use (&$count): void {
            $count++;
        });

        $lifecycle->triggerRequest();
        $lifecycle->triggerRequest();

        $this->assertSame(2, $count);
    }

    #[Test]
    public function it_calls_drain_callbacks(): void
    {
        $lifecycle = new WorkerLifecycle();
        $called = false;

        $lifecycle->onDrain(function () use (&$called): void {
            $called = true;
        });

        $lifecycle->triggerDrain();

        $this->assertTrue($called);
    }

    #[Test]
    public function it_handles_no_callbacks_gracefully(): void
    {
        $lifecycle = new WorkerLifecycle();

        // Should not throw
        $lifecycle->triggerStartup();
        $lifecycle->triggerShutdown();
        $lifecycle->triggerRequest();
        $lifecycle->triggerDrain();

        $this->assertTrue(true);
    }
}
