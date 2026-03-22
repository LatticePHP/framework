<?php

declare(strict_types=1);

namespace Lattice\Queue\Tests\Unit;

use Lattice\Queue\Driver\InMemoryDriver;
use Lattice\Queue\SerializedJob;
use Lattice\Queue\Tests\Stub\TestJob;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(InMemoryDriver::class)]
final class InMemoryDriverTest extends TestCase
{
    private InMemoryDriver $driver;

    protected function setUp(): void
    {
        $this->driver = new InMemoryDriver();
    }

    #[Test]
    public function it_pushes_and_pops_a_job(): void
    {
        $job = SerializedJob::fromJob(new TestJob('data'));
        $this->driver->push('default', $job);

        $popped = $this->driver->pop('default');

        self::assertNotNull($popped);
        self::assertSame($job->id, $popped->id);
    }

    #[Test]
    public function pop_returns_null_on_empty_queue(): void
    {
        self::assertNull($this->driver->pop('default'));
    }

    #[Test]
    public function it_follows_fifo_order(): void
    {
        $job1 = SerializedJob::fromJob(new TestJob('first'));
        $job2 = SerializedJob::fromJob(new TestJob('second'));
        $job3 = SerializedJob::fromJob(new TestJob('third'));

        $this->driver->push('default', $job1);
        $this->driver->push('default', $job2);
        $this->driver->push('default', $job3);

        self::assertSame($job1->id, $this->driver->pop('default')?->id);
        self::assertSame($job2->id, $this->driver->pop('default')?->id);
        self::assertSame($job3->id, $this->driver->pop('default')?->id);
        self::assertNull($this->driver->pop('default'));
    }

    #[Test]
    public function it_reports_correct_size(): void
    {
        self::assertSame(0, $this->driver->size('default'));

        $this->driver->push('default', SerializedJob::fromJob(new TestJob()));
        self::assertSame(1, $this->driver->size('default'));

        $this->driver->push('default', SerializedJob::fromJob(new TestJob()));
        self::assertSame(2, $this->driver->size('default'));

        $this->driver->pop('default');
        self::assertSame(1, $this->driver->size('default'));
    }

    #[Test]
    public function it_acknowledges_a_job(): void
    {
        $job = SerializedJob::fromJob(new TestJob());
        $this->driver->push('default', $job);
        $this->driver->pop('default');

        $this->driver->acknowledge($job->id);

        self::assertCount(1, $this->driver->getAcknowledged());
    }

    #[Test]
    public function it_fails_a_job(): void
    {
        $job = SerializedJob::fromJob(new TestJob());
        $this->driver->push('default', $job);
        $this->driver->pop('default');

        $this->driver->fail($job->id, new \RuntimeException('something broke'));

        self::assertCount(1, $this->driver->getFailed());
    }

    #[Test]
    public function it_isolates_queues(): void
    {
        $this->driver->push('queue-a', SerializedJob::fromJob(new TestJob()));
        $this->driver->push('queue-b', SerializedJob::fromJob(new TestJob()));

        self::assertSame(1, $this->driver->size('queue-a'));
        self::assertSame(1, $this->driver->size('queue-b'));
        self::assertSame(0, $this->driver->size('queue-c'));
    }

    #[Test]
    public function it_pushes_delayed_job(): void
    {
        $job = SerializedJob::fromJob(new TestJob());
        $id = $this->driver->pushDelayed('default', $job, 30);

        self::assertNotEmpty($id);
        self::assertSame(1, $this->driver->size('default'));
    }

    #[Test]
    public function it_tracks_all_dispatched_jobs(): void
    {
        $this->driver->push('default', SerializedJob::fromJob(new TestJob()));
        $this->driver->push('emails', SerializedJob::fromJob(new TestJob()));

        self::assertCount(2, $this->driver->getDispatched());
    }
}
