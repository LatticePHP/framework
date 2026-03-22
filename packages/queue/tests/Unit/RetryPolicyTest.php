<?php

declare(strict_types=1);

namespace Lattice\Queue\Tests\Unit;

use Lattice\Queue\RetryPolicy;
use Lattice\Queue\SerializedJob;
use Lattice\Queue\Tests\Stub\FailingJob;
use Lattice\Queue\Tests\Stub\TestJob;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(RetryPolicy::class)]
final class RetryPolicyTest extends TestCase
{
    private RetryPolicy $policy;

    protected function setUp(): void
    {
        $this->policy = new RetryPolicy();
    }

    #[Test]
    public function it_allows_retry_when_attempts_below_max(): void
    {
        $job = new SerializedJob(
            id: 'job-1',
            queue: 'default',
            payload: serialize(new TestJob()),
            attempts: 1,
            maxAttempts: 3,
            timeout: 60,
            availableAt: new \DateTimeImmutable(),
            createdAt: new \DateTimeImmutable(),
        );

        self::assertTrue($this->policy->shouldRetry($job));
    }

    #[Test]
    public function it_denies_retry_when_attempts_at_max(): void
    {
        $job = new SerializedJob(
            id: 'job-1',
            queue: 'default',
            payload: serialize(new TestJob()),
            attempts: 3,
            maxAttempts: 3,
            timeout: 60,
            availableAt: new \DateTimeImmutable(),
            createdAt: new \DateTimeImmutable(),
        );

        self::assertFalse($this->policy->shouldRetry($job));
    }

    #[Test]
    public function it_denies_retry_when_attempts_exceed_max(): void
    {
        $job = new SerializedJob(
            id: 'job-1',
            queue: 'default',
            payload: serialize(new TestJob()),
            attempts: 5,
            maxAttempts: 3,
            timeout: 60,
            availableAt: new \DateTimeImmutable(),
            createdAt: new \DateTimeImmutable(),
        );

        self::assertFalse($this->policy->shouldRetry($job));
    }

    #[Test]
    public function it_calculates_backoff_delay_for_first_attempt(): void
    {
        $job = new SerializedJob(
            id: 'job-1',
            queue: 'default',
            payload: serialize(new TestJob()),
            attempts: 1,
            maxAttempts: 3,
            timeout: 60,
            availableAt: new \DateTimeImmutable(),
            createdAt: new \DateTimeImmutable(),
        );

        // TestJob backoff = [1, 5, 30], attempt 1 -> index 0 -> 1 second
        self::assertSame(1, $this->policy->getDelay($job));
    }

    #[Test]
    public function it_calculates_backoff_delay_for_second_attempt(): void
    {
        $job = new SerializedJob(
            id: 'job-1',
            queue: 'default',
            payload: serialize(new TestJob()),
            attempts: 2,
            maxAttempts: 3,
            timeout: 60,
            availableAt: new \DateTimeImmutable(),
            createdAt: new \DateTimeImmutable(),
        );

        // TestJob backoff = [1, 5, 30], attempt 2 -> index 1 -> 5 seconds
        self::assertSame(5, $this->policy->getDelay($job));
    }

    #[Test]
    public function it_uses_last_backoff_value_when_attempts_exceed_array(): void
    {
        $job = new SerializedJob(
            id: 'job-1',
            queue: 'default',
            payload: serialize(new TestJob()),
            attempts: 10,
            maxAttempts: 15,
            timeout: 60,
            availableAt: new \DateTimeImmutable(),
            createdAt: new \DateTimeImmutable(),
        );

        // TestJob backoff = [1, 5, 30], attempt 10 -> capped at last -> 30
        self::assertSame(30, $this->policy->getDelay($job));
    }

    #[Test]
    public function it_returns_zero_delay_for_zero_attempts(): void
    {
        $job = new SerializedJob(
            id: 'job-1',
            queue: 'default',
            payload: serialize(new TestJob()),
            attempts: 0,
            maxAttempts: 3,
            timeout: 60,
            availableAt: new \DateTimeImmutable(),
            createdAt: new \DateTimeImmutable(),
        );

        self::assertSame(0, $this->policy->getDelay($job));
    }
}
