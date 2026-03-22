<?php

declare(strict_types=1);

namespace Lattice\Queue\Tests\Fixtures;

use Lattice\Queue\AbstractJob;

/**
 * A job that fails for the first N attempts, then succeeds.
 * Uses a static counter keyed by job ID to track attempts across serialization boundaries.
 */
final class FailingThenSucceedingJob extends AbstractJob
{
    /** @var array<string, int> */
    public static array $attemptCounts = [];

    /** @var bool */
    public static bool $succeeded = false;

    protected int $maxAttempts = 3;

    /** @var int[] */
    protected array $backoff = [1, 5, 30];

    public function __construct(
        public readonly string $jobKey = 'default',
        public readonly int $failForAttempts = 1,
    ) {
    }

    public function handle(): void
    {
        if (!isset(self::$attemptCounts[$this->jobKey])) {
            self::$attemptCounts[$this->jobKey] = 0;
        }

        self::$attemptCounts[$this->jobKey]++;

        if (self::$attemptCounts[$this->jobKey] <= $this->failForAttempts) {
            throw new \RuntimeException(
                "Intentional failure on attempt " . self::$attemptCounts[$this->jobKey]
            );
        }

        self::$succeeded = true;
    }

    public static function reset(): void
    {
        self::$attemptCounts = [];
        self::$succeeded = false;
    }
}
