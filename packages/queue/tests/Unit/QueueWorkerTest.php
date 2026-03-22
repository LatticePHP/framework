<?php

declare(strict_types=1);

namespace Lattice\Queue\Tests\Unit;

use Lattice\Queue\AbstractJob;
use Lattice\Queue\Driver\QueueDriverInterface;
use Lattice\Queue\Driver\SyncDriver;
use Lattice\Queue\Failed\InMemoryFailedJobStore;
use Lattice\Queue\RetryPolicy;
use Lattice\Queue\SerializedJob;
use Lattice\Queue\Worker;
use Lattice\Queue\WorkerOptions;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class QueueWorkerTest extends TestCase
{
    #[Test]
    public function test_worker_processes_job_from_queue(): void
    {
        $job = new WorkerTestJob();
        $serialized = SerializedJob::fromJob($job);

        $driver = new InMemoryQueueDriver();
        $driver->enqueue($serialized);

        $worker = new Worker(
            driver: $driver,
            retryPolicy: new RetryPolicy(),
            failedJobStore: new InMemoryFailedJobStore(),
        );

        $options = new WorkerOptions(
            queue: 'default',
            maxJobs: 1,
        );

        $worker->work($options);

        // Job should have been popped and acknowledged
        self::assertTrue($driver->wasAcknowledged($serialized->id));
        self::assertSame(0, $driver->size('default'));
    }

    #[Test]
    public function test_worker_retries_failed_job(): void
    {
        $job = new FailingWorkerTestJob();
        $serialized = SerializedJob::fromJob($job);

        $driver = new InMemoryQueueDriver();
        $driver->enqueue($serialized);

        $failedStore = new InMemoryFailedJobStore();

        $worker = new Worker(
            driver: $driver,
            retryPolicy: new RetryPolicy(),
            failedJobStore: $failedStore,
        );

        $options = new WorkerOptions(
            queue: 'default',
            maxJobs: 1,
        );

        $worker->work($options);

        // Job failed but should be retried (pushed back with delay)
        self::assertFalse($driver->wasAcknowledged($serialized->id));
        // RetryPolicy allows retry since attempts < maxAttempts (3)
        self::assertCount(1, $driver->getDelayedJobs());
    }

    #[Test]
    public function test_worker_stores_permanently_failed_job(): void
    {
        $job = new FailingWorkerTestJob();
        $job->maxAttempts = 1; // Only 1 attempt allowed
        $serialized = SerializedJob::fromJob($job);

        $driver = new InMemoryQueueDriver();
        $driver->enqueue($serialized);

        $failedStore = new InMemoryFailedJobStore();

        $worker = new Worker(
            driver: $driver,
            retryPolicy: new RetryPolicy(),
            failedJobStore: $failedStore,
        );

        $options = new WorkerOptions(
            queue: 'default',
            maxJobs: 1,
        );

        $worker->work($options);

        // Job should be in the failed store since max attempts exhausted
        self::assertCount(1, $failedStore->all());
    }

    #[Test]
    public function test_worker_stops_after_max_jobs(): void
    {
        $driver = new InMemoryQueueDriver();

        for ($i = 0; $i < 5; $i++) {
            $driver->enqueue(SerializedJob::fromJob(new WorkerTestJob()));
        }

        $worker = new Worker(
            driver: $driver,
            retryPolicy: new RetryPolicy(),
            failedJobStore: new InMemoryFailedJobStore(),
        );

        $options = new WorkerOptions(
            queue: 'default',
            maxJobs: 3,
        );

        $worker->work($options);

        // Only 3 of 5 jobs should have been processed
        self::assertSame(2, $driver->size('default'));
    }
}

final class WorkerTestJob extends AbstractJob
{
    public function handle(): void
    {
        // Successful no-op job
    }
}

final class FailingWorkerTestJob extends AbstractJob
{
    public int $maxAttempts = 3;

    public function handle(): void
    {
        throw new \RuntimeException('Job failed intentionally');
    }

    public function getMaxAttempts(): int
    {
        return $this->maxAttempts;
    }
}

/**
 * In-memory queue driver for testing that tracks pops, acks, and delays.
 */
final class InMemoryQueueDriver implements QueueDriverInterface
{
    /** @var SerializedJob[] */
    private array $jobs = [];

    /** @var array<string, true> */
    private array $acknowledged = [];

    /** @var SerializedJob[] */
    private array $delayedJobs = [];

    /** @var array<string, true> */
    private array $failed = [];

    public function enqueue(SerializedJob $job): void
    {
        $this->jobs[] = $job;
    }

    public function push(string $queue, SerializedJob $job): string
    {
        $this->jobs[] = $job;

        return $job->id;
    }

    public function pushDelayed(string $queue, SerializedJob $job, int $delaySeconds): string
    {
        $this->delayedJobs[] = $job;

        return $job->id;
    }

    public function pop(string $queue): ?SerializedJob
    {
        if ($this->jobs === []) {
            return null;
        }

        return array_shift($this->jobs);
    }

    public function acknowledge(string $jobId): void
    {
        $this->acknowledged[$jobId] = true;
    }

    public function fail(string $jobId, \Throwable $reason): void
    {
        $this->failed[$jobId] = true;
    }

    public function size(string $queue): int
    {
        return count($this->jobs);
    }

    public function wasAcknowledged(string $jobId): bool
    {
        return isset($this->acknowledged[$jobId]);
    }

    /** @return SerializedJob[] */
    public function getDelayedJobs(): array
    {
        return $this->delayedJobs;
    }
}
