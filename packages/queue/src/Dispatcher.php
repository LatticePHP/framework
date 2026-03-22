<?php

declare(strict_types=1);

namespace Lattice\Queue;

use Lattice\Queue\Driver\QueueDriverInterface;

final class Dispatcher
{
    public function __construct(
        private readonly QueueDriverInterface $driver,
    ) {
    }

    public function dispatch(JobInterface $job): string
    {
        $serialized = SerializedJob::fromJob($job);

        return $this->driver->push($job->getQueue(), $serialized);
    }

    public function dispatchAfter(JobInterface $job, int $delaySeconds): string
    {
        $serialized = SerializedJob::fromJob($job, $delaySeconds);

        return $this->driver->pushDelayed($job->getQueue(), $serialized, $delaySeconds);
    }
}
