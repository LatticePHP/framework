<?php

declare(strict_types=1);

namespace Lattice\Queue\Tests\Unit;

use Lattice\Queue\Driver\InMemoryDriver;
use Lattice\Queue\Failed\InMemoryFailedJobStore;
use Lattice\Queue\RetryPolicy;
use Lattice\Queue\SerializedJob;
use Lattice\Queue\Tests\Stub\FailingJob;
use Lattice\Queue\Tests\Stub\TestJob;
use Lattice\Queue\Worker;
use Lattice\Queue\WorkerOptions;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Worker::class)]
#[CoversClass(WorkerOptions::class)]
final class WorkerTest extends TestCase
{
    private InMemoryDriver $driver;
    private InMemoryFailedJobStore $failedStore;
    private Worker $worker;

    protected function setUp(): void
    {
        $this->driver = new InMemoryDriver();
        $this->failedStore = new InMemoryFailedJobStore();
        $this->worker = new Worker(
            $this->driver,
            new RetryPolicy(),
            $this->failedStore,
        );
    }

    #[Test]
    public function it_processes_a_single_job(): void
    {
        $job = SerializedJob::fromJob(new TestJob('worker-test'));
        $this->driver->push('default', $job);

        $options = new WorkerOptions(queue: 'default', maxJobs: 1);
        $this->worker->work($options);

        self::assertSame(0, $this->driver->size('default'));
        self::assertCount(1, $this->driver->getAcknowledged());
    }

    #[Test]
    public function it_processes_multiple_jobs(): void
    {
        $this->driver->push('default', SerializedJob::fromJob(new TestJob('one')));
        $this->driver->push('default', SerializedJob::fromJob(new TestJob('two')));
        $this->driver->push('default', SerializedJob::fromJob(new TestJob('three')));

        $options = new WorkerOptions(queue: 'default', maxJobs: 3);
        $this->worker->work($options);

        self::assertSame(0, $this->driver->size('default'));
        self::assertCount(3, $this->driver->getAcknowledged());
    }

    #[Test]
    public function it_retries_failed_job_within_max_attempts(): void
    {
        $job = new FailingJob('will-retry');
        $serialized = SerializedJob::fromJob($job);
        $this->driver->push('default', $serialized);

        $options = new WorkerOptions(queue: 'default', maxJobs: 1);
        $this->worker->work($options);

        // Job should be pushed back for retry (attempts < maxAttempts)
        self::assertSame(1, $this->driver->size('default'));
        self::assertCount(0, $this->driver->getAcknowledged());
    }

    #[Test]
    public function it_moves_to_failed_after_max_attempts(): void
    {
        $job = new FailingJob('final-fail');
        $serialized = new SerializedJob(
            id: $serialized = 'fail-' . bin2hex(random_bytes(8)),
            queue: 'default',
            payload: serialize($job),
            attempts: 2, // already at attempts 2, max is 3, so next attempt is 3 = max
            maxAttempts: 3,
            timeout: 60,
            availableAt: new \DateTimeImmutable(),
            createdAt: new \DateTimeImmutable(),
        );
        $this->driver->push('default', $serialized);

        $options = new WorkerOptions(queue: 'default', maxJobs: 1);
        $this->worker->work($options);

        // Should NOT be retried, should be in failed store
        self::assertSame(0, $this->driver->size('default'));
        self::assertCount(1, $this->failedStore->all());
    }

    #[Test]
    public function it_stops_after_max_jobs(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->driver->push('default', SerializedJob::fromJob(new TestJob("job-$i")));
        }

        $options = new WorkerOptions(queue: 'default', maxJobs: 3);
        $this->worker->work($options);

        self::assertSame(2, $this->driver->size('default'));
        self::assertCount(3, $this->driver->getAcknowledged());
    }

    #[Test]
    public function it_stops_when_queue_is_empty(): void
    {
        $this->driver->push('default', SerializedJob::fromJob(new TestJob()));

        // maxJobs=10 but only 1 job available, should stop gracefully
        $options = new WorkerOptions(queue: 'default', maxJobs: 10, sleep: 0);
        $this->worker->work($options);

        self::assertCount(1, $this->driver->getAcknowledged());
    }

    #[Test]
    public function worker_options_has_defaults(): void
    {
        $options = new WorkerOptions();

        self::assertSame('default', $options->queue);
        self::assertSame('default', $options->connection);
        self::assertSame(0, $options->maxJobs);
        self::assertSame(0, $options->maxTime);
        self::assertSame(3, $options->sleep);
        self::assertSame(128, $options->memoryLimit);
    }

    #[Test]
    public function worker_options_accepts_custom_values(): void
    {
        $options = new WorkerOptions(
            queue: 'emails',
            connection: 'redis',
            maxJobs: 100,
            maxTime: 3600,
            sleep: 1,
            memoryLimit: 256,
        );

        self::assertSame('emails', $options->queue);
        self::assertSame('redis', $options->connection);
        self::assertSame(100, $options->maxJobs);
        self::assertSame(3600, $options->maxTime);
        self::assertSame(1, $options->sleep);
        self::assertSame(256, $options->memoryLimit);
    }
}
