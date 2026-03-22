<?php

declare(strict_types=1);

namespace Lattice\Queue\Tests\Unit;

use Lattice\Queue\Testing\QueueFake;
use Lattice\Queue\Tests\Stub\FailingJob;
use Lattice\Queue\Tests\Stub\TestJob;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(QueueFake::class)]
final class QueueFakeTest extends TestCase
{
    private QueueFake $fake;

    protected function setUp(): void
    {
        $this->fake = new QueueFake();
    }

    #[Test]
    public function it_asserts_dispatched(): void
    {
        $this->fake->dispatch(new TestJob());

        $this->fake->assertDispatched(TestJob::class);
    }

    #[Test]
    public function it_asserts_not_dispatched(): void
    {
        $this->fake->assertNotDispatched(TestJob::class);
    }

    #[Test]
    public function it_fails_assert_dispatched_when_not_dispatched(): void
    {
        $this->expectException(AssertionFailedError::class);

        $this->fake->assertDispatched(TestJob::class);
    }

    #[Test]
    public function it_fails_assert_not_dispatched_when_dispatched(): void
    {
        $this->fake->dispatch(new TestJob());

        $this->expectException(AssertionFailedError::class);

        $this->fake->assertNotDispatched(TestJob::class);
    }

    #[Test]
    public function it_asserts_dispatched_count(): void
    {
        $this->fake->dispatch(new TestJob());
        $this->fake->dispatch(new TestJob());
        $this->fake->dispatch(new TestJob());

        $this->fake->assertDispatchedCount(TestJob::class, 3);
    }

    #[Test]
    public function it_fails_assert_dispatched_count_when_mismatch(): void
    {
        $this->fake->dispatch(new TestJob());

        $this->expectException(AssertionFailedError::class);

        $this->fake->assertDispatchedCount(TestJob::class, 5);
    }

    #[Test]
    public function it_returns_dispatched_jobs(): void
    {
        $job1 = new TestJob('one');
        $job2 = new TestJob('two');
        $this->fake->dispatch($job1);
        $this->fake->dispatch($job2);

        $dispatched = $this->fake->getDispatched(TestJob::class);

        self::assertCount(2, $dispatched);
        self::assertSame('one', $dispatched[0]->data);
        self::assertSame('two', $dispatched[1]->data);
    }

    #[Test]
    public function it_isolates_different_job_classes(): void
    {
        $this->fake->dispatch(new TestJob());
        $this->fake->dispatch(new FailingJob());

        $this->fake->assertDispatchedCount(TestJob::class, 1);
        $this->fake->assertDispatchedCount(FailingJob::class, 1);
    }

    #[Test]
    public function it_returns_empty_array_for_undispatched_class(): void
    {
        $dispatched = $this->fake->getDispatched(TestJob::class);

        self::assertSame([], $dispatched);
    }

    #[Test]
    public function dispatch_returns_an_id(): void
    {
        $id = $this->fake->dispatch(new TestJob());

        self::assertNotEmpty($id);
    }

    #[Test]
    public function dispatch_after_records_job(): void
    {
        $this->fake->dispatchAfter(new TestJob(), 30);

        $this->fake->assertDispatched(TestJob::class);
    }
}
