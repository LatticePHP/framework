<?php

declare(strict_types=1);

namespace Lattice\Queue\Tests\Fixtures;

use Lattice\Queue\AbstractJob;

/**
 * A job with custom backoff values, used to verify delay calculations.
 */
final class BackoffTrackingJob extends AbstractJob
{
    protected int $maxAttempts = 4;

    /** @var int[] */
    protected array $backoff = [2, 10, 60];

    public function __construct()
    {
    }

    public function handle(): void
    {
        throw new \RuntimeException('Intentional failure for backoff tracking');
    }
}
