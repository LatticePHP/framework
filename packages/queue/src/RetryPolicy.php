<?php

declare(strict_types=1);

namespace Lattice\Queue;

final class RetryPolicy
{
    public function shouldRetry(SerializedJob $job): bool
    {
        return $job->attempts < $job->maxAttempts;
    }

    public function getDelay(SerializedJob $job): int
    {
        if ($job->attempts <= 0) {
            return 0;
        }

        $backoff = $job->toJob()->getBackoff();

        if ($backoff === []) {
            return 0;
        }

        $index = $job->attempts - 1;

        return $backoff[min($index, count($backoff) - 1)];
    }
}
