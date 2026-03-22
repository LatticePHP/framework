<?php

declare(strict_types=1);

namespace Lattice\Queue\Tests\Unit;

use Lattice\Queue\Driver\SyncDriver;
use Lattice\Queue\SerializedJob;
use Lattice\Queue\Tests\Stub\FailingJob;
use Lattice\Queue\Tests\Stub\TestJob;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(SyncDriver::class)]
final class SyncDriverTest extends TestCase
{
    private SyncDriver $driver;

    protected function setUp(): void
    {
        $this->driver = new SyncDriver();
    }

    #[Test]
    public function it_executes_job_immediately_on_push(): void
    {
        $job = new TestJob('sync-test');
        $serialized = SerializedJob::fromJob($job);

        $id = $this->driver->push('default', $serialized);

        self::assertNotEmpty($id);
    }

    #[Test]
    public function it_pop_returns_null(): void
    {
        self::assertNull($this->driver->pop('default'));
    }

    #[Test]
    public function it_reports_zero_size(): void
    {
        $job = new TestJob();
        $serialized = SerializedJob::fromJob($job);
        $this->driver->push('default', $serialized);

        self::assertSame(0, $this->driver->size('default'));
    }

    #[Test]
    public function it_throws_on_failing_job(): void
    {
        $job = new FailingJob('sync fail');
        $serialized = SerializedJob::fromJob($job);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('sync fail');

        $this->driver->push('default', $serialized);
    }

    #[Test]
    public function it_executes_delayed_job_immediately(): void
    {
        $job = new TestJob('delayed-sync');
        $serialized = SerializedJob::fromJob($job);

        $id = $this->driver->pushDelayed('default', $serialized, 30);

        self::assertNotEmpty($id);
    }

    #[Test]
    public function acknowledge_is_noop(): void
    {
        $this->driver->acknowledge('non-existent');
        $this->addToAssertionCount(1);
    }

    #[Test]
    public function fail_is_noop(): void
    {
        $this->driver->fail('non-existent', new \RuntimeException('test'));
        $this->addToAssertionCount(1);
    }
}
