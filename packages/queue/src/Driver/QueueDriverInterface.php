<?php

declare(strict_types=1);

namespace Lattice\Queue\Driver;

use Lattice\Queue\SerializedJob;

interface QueueDriverInterface
{
    public function push(string $queue, SerializedJob $job): string;

    public function pushDelayed(string $queue, SerializedJob $job, int $delaySeconds): string;

    public function pop(string $queue): ?SerializedJob;

    public function acknowledge(string $jobId): void;

    public function fail(string $jobId, \Throwable $reason): void;

    public function size(string $queue): int;
}
