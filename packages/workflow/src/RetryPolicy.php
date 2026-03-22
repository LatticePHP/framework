<?php

declare(strict_types=1);

namespace Lattice\Workflow;

use Lattice\Contracts\Workflow\RetryPolicyInterface;

final class RetryPolicy implements RetryPolicyInterface
{
    /**
     * @param array<class-string<\Throwable>> $nonRetryableExceptions
     */
    public function __construct(
        private readonly int $maxAttempts = 3,
        private readonly int $initialInterval = 1,
        private readonly float $backoffCoefficient = 2.0,
        private readonly ?int $maxInterval = 60,
        private readonly array $nonRetryableExceptions = [],
    ) {}

    public function getMaxAttempts(): int
    {
        return $this->maxAttempts;
    }

    public function getInitialInterval(): int
    {
        return $this->initialInterval;
    }

    public function getBackoffCoefficient(): float
    {
        return $this->backoffCoefficient;
    }

    public function getMaxInterval(): ?int
    {
        return $this->maxInterval;
    }

    /** @return array<class-string<\Throwable>> */
    public function getNonRetryableExceptions(): array
    {
        return $this->nonRetryableExceptions;
    }
}
