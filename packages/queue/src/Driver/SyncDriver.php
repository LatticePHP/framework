<?php

declare(strict_types=1);

namespace Lattice\Queue\Driver;

use Lattice\Queue\SerializedJob;

final class SyncDriver implements QueueDriverInterface
{
    public function push(string $queue, SerializedJob $job): string
    {
        $instance = $job->toJob();
        $instance->handle();

        return $job->id;
    }

    public function pushDelayed(string $queue, SerializedJob $job, int $delaySeconds): string
    {
        // In sync mode, delay is ignored — execute immediately
        return $this->push($queue, $job);
    }

    public function pop(string $queue): ?SerializedJob
    {
        return null;
    }

    public function acknowledge(string $jobId): void
    {
        // no-op: job was already executed synchronously
    }

    public function fail(string $jobId, \Throwable $reason): void
    {
        // no-op: failures propagate as exceptions from push()
    }

    public function size(string $queue): int
    {
        return 0;
    }
}
