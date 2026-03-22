<?php

declare(strict_types=1);

namespace Lattice\Queue\Tests\Unit;

use Lattice\Queue\AbstractJob;
use Lattice\Queue\Dispatcher;
use Lattice\Queue\Driver\SyncDriver;
use Lattice\Queue\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class QueueFacadeTest extends TestCase
{
    protected function setUp(): void
    {
        Queue::reset();
    }

    protected function tearDown(): void
    {
        Queue::reset();
    }

    #[Test]
    public function test_dispatch_queues_job_through_facade(): void
    {
        $driver = new SyncDriver();
        $dispatcher = new Dispatcher($driver);
        Queue::setInstance($dispatcher);

        $job = new QueueFacadeTestJob();

        // SyncDriver executes immediately via serialization/deserialization.
        // The deserialized copy runs handle(), not the original object.
        $id = Queue::dispatch($job);

        self::assertNotEmpty($id);
        // Verify a job ID was returned (sync driver returns the serialized job ID)
        self::assertSame(32, strlen($id)); // bin2hex(16 bytes) = 32 chars
    }

    #[Test]
    public function test_later_dispatches_delayed_job(): void
    {
        $driver = new SyncDriver();
        $dispatcher = new Dispatcher($driver);
        Queue::setInstance($dispatcher);

        $job = new QueueFacadeTestJob();

        // SyncDriver ignores delay and runs immediately
        $id = Queue::later(60, $job);

        self::assertNotEmpty($id);
        self::assertSame(32, strlen($id));
    }

    #[Test]
    public function test_throws_when_not_initialized(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Queue facade has not been initialized');

        Queue::dispatch(new QueueFacadeTestJob());
    }

    #[Test]
    public function test_get_instance_returns_dispatcher(): void
    {
        $driver = new SyncDriver();
        $dispatcher = new Dispatcher($driver);
        Queue::setInstance($dispatcher);

        self::assertSame($dispatcher, Queue::getInstance());
    }

    #[Test]
    public function test_reset_clears_instance(): void
    {
        $driver = new SyncDriver();
        $dispatcher = new Dispatcher($driver);
        Queue::setInstance($dispatcher);

        Queue::reset();

        $this->expectException(\RuntimeException::class);
        Queue::getInstance();
    }
}

final class QueueFacadeTestJob extends AbstractJob
{
    public bool $wasHandled = false;

    public function handle(): void
    {
        $this->wasHandled = true;
    }
}
