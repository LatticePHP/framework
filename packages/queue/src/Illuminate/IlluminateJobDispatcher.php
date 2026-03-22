<?php

declare(strict_types=1);

namespace Lattice\Queue\Illuminate;

use Lattice\Queue\JobInterface;
use Illuminate\Queue\QueueManager;
use Illuminate\Contracts\Queue\Queue;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Bridges Lattice's JobInterface with Illuminate's queue system.
 *
 * Accepts Lattice jobs and dispatches them through Illuminate's QueueManager,
 * handling queue routing, delays, and connection selection.
 */
final class IlluminateJobDispatcher
{
    public function __construct(
        private readonly IlluminateQueueManager $queueManager,
    ) {
    }

    /**
     * Dispatch a Lattice job through Illuminate's queue.
     */
    public function dispatch(JobInterface $job): mixed
    {
        $wrappedJob = new IlluminateJobWrapper($job);

        return $this->queueManager->push(
            $wrappedJob,
            queue: $job->getQueue(),
        );
    }

    /**
     * Dispatch a Lattice job with a delay.
     */
    public function dispatchAfter(JobInterface $job, \DateTimeInterface|\DateInterval|int $delay): mixed
    {
        $wrappedJob = new IlluminateJobWrapper($job);

        return $this->queueManager->later(
            $delay,
            $wrappedJob,
            queue: $job->getQueue(),
        );
    }

    /**
     * Dispatch a job to a specific connection.
     */
    public function dispatchOn(JobInterface $job, string $connection): mixed
    {
        $queue = $this->queueManager->connection($connection);

        return $queue->push(new IlluminateJobWrapper($job), queue: $job->getQueue());
    }
}
