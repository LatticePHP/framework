<?php

declare(strict_types=1);

namespace Lattice\Queue\Tests\Fixtures;

use Lattice\Queue\AbstractJob;

/**
 * A job that always fails, used to test max retries exhaustion.
 */
final class AlwaysFailingJob extends AbstractJob
{
    /** @var int */
    public static int $handleCount = 0;

    protected int $maxAttempts = 2;

    public function __construct(
        public readonly string $errorMessage = 'This job always fails',
    ) {
    }

    public function handle(): void
    {
        self::$handleCount++;
        throw new \RuntimeException($this->errorMessage);
    }

    public static function reset(): void
    {
        self::$handleCount = 0;
    }
}
