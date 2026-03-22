<?php

declare(strict_types=1);

namespace Lattice\Queue\Tests\Unit;

use Lattice\Queue\Dispatcher;
use Lattice\Queue\Driver\InMemoryDriver;
use Lattice\Queue\Tests\Stub\TestJob;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Dispatcher::class)]
final class DispatcherTest extends TestCase
{
    private InMemoryDriver $driver;
    private Dispatcher $dispatcher;

    protected function setUp(): void
    {
        $this->driver = new InMemoryDriver();
        $this->dispatcher = new Dispatcher($this->driver);
    }

    #[Test]
    public function it_dispatches_a_job_and_returns_id(): void
    {
        $job = new TestJob('dispatch-test');
        $id = $this->dispatcher->dispatch($job);

        self::assertNotEmpty($id);
        self::assertSame(1, $this->driver->size('default'));
    }

    #[Test]
    public function it_dispatches_to_correct_queue(): void
    {
        $job = new TestJob('queue-test');
        $this->dispatcher->dispatch($job);

        self::assertSame(1, $this->driver->size('default'));
        self::assertSame(0, $this->driver->size('other'));
    }

    #[Test]
    public function it_dispatches_with_delay(): void
    {
        $job = new TestJob('delayed');
        $id = $this->dispatcher->dispatchAfter($job, 30);

        self::assertNotEmpty($id);
        self::assertSame(1, $this->driver->size('default'));
    }

    #[Test]
    public function it_dispatches_multiple_jobs(): void
    {
        $this->dispatcher->dispatch(new TestJob('one'));
        $this->dispatcher->dispatch(new TestJob('two'));
        $this->dispatcher->dispatch(new TestJob('three'));

        self::assertSame(3, $this->driver->size('default'));
    }
}
