<?php

declare(strict_types=1);

namespace Lattice\Queue\Tests\Unit;

use Lattice\Queue\SerializedJob;
use Lattice\Queue\Tests\Stub\TestJob;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(SerializedJob::class)]
final class SerializedJobTest extends TestCase
{
    #[Test]
    public function it_creates_a_serialized_job_with_all_properties(): void
    {
        $now = new \DateTimeImmutable();
        $job = new SerializedJob(
            id: 'job-123',
            queue: 'default',
            payload: 'serialized-data',
            attempts: 0,
            maxAttempts: 3,
            timeout: 60,
            availableAt: $now,
            createdAt: $now,
        );

        self::assertSame('job-123', $job->id);
        self::assertSame('default', $job->queue);
        self::assertSame('serialized-data', $job->payload);
        self::assertSame(0, $job->attempts);
        self::assertSame(3, $job->maxAttempts);
        self::assertSame(60, $job->timeout);
        self::assertSame($now, $job->availableAt);
        self::assertSame($now, $job->createdAt);
    }

    #[Test]
    public function it_creates_from_job_interface(): void
    {
        $job = new TestJob('hello');
        $serialized = SerializedJob::fromJob($job);

        self::assertNotEmpty($serialized->id);
        self::assertSame('default', $serialized->queue);
        self::assertSame(0, $serialized->attempts);
        self::assertSame(3, $serialized->maxAttempts);
        self::assertSame(60, $serialized->timeout);
        self::assertNotEmpty($serialized->payload);
    }

    #[Test]
    public function it_deserializes_back_to_job(): void
    {
        $original = new TestJob('round-trip');
        $serialized = SerializedJob::fromJob($original);
        $restored = $serialized->toJob();

        self::assertInstanceOf(TestJob::class, $restored);
        self::assertSame('round-trip', $restored->data);
    }

    #[Test]
    public function it_increments_attempts(): void
    {
        $job = new SerializedJob(
            id: 'job-1',
            queue: 'default',
            payload: 'data',
            attempts: 1,
            maxAttempts: 3,
            timeout: 60,
            availableAt: new \DateTimeImmutable(),
            createdAt: new \DateTimeImmutable(),
        );

        $incremented = $job->withIncrementedAttempts();

        self::assertSame(2, $incremented->attempts);
        self::assertSame(1, $job->attempts); // original unchanged
    }

    #[Test]
    public function it_creates_with_delay(): void
    {
        $job = new TestJob();
        $serialized = SerializedJob::fromJob($job, delaySeconds: 30);

        $expectedMin = new \DateTimeImmutable('+29 seconds');
        $expectedMax = new \DateTimeImmutable('+31 seconds');

        self::assertGreaterThanOrEqual($expectedMin, $serialized->availableAt);
        self::assertLessThanOrEqual($expectedMax, $serialized->availableAt);
    }

    #[Test]
    public function it_updates_available_at(): void
    {
        $now = new \DateTimeImmutable();
        $later = new \DateTimeImmutable('+60 seconds');

        $job = new SerializedJob(
            id: 'job-1',
            queue: 'default',
            payload: 'data',
            attempts: 0,
            maxAttempts: 3,
            timeout: 60,
            availableAt: $now,
            createdAt: $now,
        );

        $delayed = $job->withAvailableAt($later);

        self::assertSame($later, $delayed->availableAt);
        self::assertSame($now, $job->availableAt);
    }
}
