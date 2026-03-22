<?php

declare(strict_types=1);

namespace Lattice\Queue;

final class WorkerOptions
{
    public function __construct(
        public readonly string $queue = 'default',
        public readonly string $connection = 'default',
        public readonly int $maxJobs = 0,
        public readonly int $maxTime = 0,
        public readonly int $sleep = 3,
        public readonly int $memoryLimit = 128,
    ) {
    }
}
