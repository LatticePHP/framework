<?php

declare(strict_types=1);

namespace Lattice\Queue\Tests\Stub;

use Lattice\Queue\AbstractJob;

final class CustomQueueJob extends AbstractJob
{
    protected string $queue = 'emails';
    protected string $connection = 'redis';
    protected int $maxAttempts = 5;
    protected int $timeout = 120;
    /** @var int[] */
    protected array $backoff = [2, 10, 60];

    public function handle(): void
    {
        // no-op
    }
}
