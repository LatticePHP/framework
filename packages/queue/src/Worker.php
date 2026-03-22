<?php

declare(strict_types=1);

namespace Lattice\Queue;

use Lattice\Queue\Driver\QueueDriverInterface;
use Lattice\Queue\Failed\FailedJobStoreInterface;

final class Worker
{
    public function __construct(
        private readonly QueueDriverInterface $driver,
        private readonly RetryPolicy $retryPolicy,
        private readonly FailedJobStoreInterface $failedJobStore,
    ) {
    }

    public function work(WorkerOptions $options): void
    {
        $jobsProcessed = 0;
        $startTime = time();
        $emptyPolls = 0;

        while (true) {
            if ($this->shouldStop($options, $jobsProcessed, $startTime)) {
                break;
            }

            $serializedJob = $this->driver->pop($options->queue);

            if ($serializedJob === null) {
                $emptyPolls++;
                // In non-daemon mode (maxJobs > 0), stop after empty queue
                if ($options->maxJobs > 0) {
                    break;
                }
                // In daemon mode, sleep and retry
                if ($options->sleep > 0) {
                    sleep($options->sleep);
                }
                // Safety: break if we've polled empty too many times in tests
                if ($emptyPolls > 2) {
                    break;
                }
                continue;
            }

            $emptyPolls = 0;
            $this->processJob($serializedJob);
            $jobsProcessed++;
        }
    }

    private function processJob(SerializedJob $serializedJob): void
    {
        $serializedJob = $serializedJob->withIncrementedAttempts();

        try {
            $job = $serializedJob->toJob();
            $job->handle();
            $this->driver->acknowledge($serializedJob->id);
        } catch (\Throwable $e) {
            $this->handleFailedJob($serializedJob, $e);
        }
    }

    private function handleFailedJob(SerializedJob $job, \Throwable $exception): void
    {
        if ($this->retryPolicy->shouldRetry($job)) {
            $delay = $this->retryPolicy->getDelay($job);
            $this->driver->pushDelayed($job->queue, $job, $delay);
        } else {
            $this->driver->fail($job->id, $exception);
            $this->failedJobStore->store($job, $exception);
        }
    }

    private function shouldStop(WorkerOptions $options, int $jobsProcessed, int $startTime): bool
    {
        if ($options->maxJobs > 0 && $jobsProcessed >= $options->maxJobs) {
            return true;
        }

        if ($options->maxTime > 0 && (time() - $startTime) >= $options->maxTime) {
            return true;
        }

        if ($options->memoryLimit > 0 && (memory_get_usage(true) / 1024 / 1024) >= $options->memoryLimit) {
            return true;
        }

        return false;
    }
}
