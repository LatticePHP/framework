<?php

declare(strict_types=1);

namespace Lattice\Core\CircuitBreaker;

final class Circuit
{
    private string $state = 'closed';
    private int $failureCount = 0;
    private int $successCount = 0;
    private ?float $openedAt = null;

    public function __construct(
        private readonly int $failureThreshold = 5,
        private readonly int $successThreshold = 2,
        private readonly int $timeout = 30,
    ) {}

    public function isOpen(): bool
    {
        if ($this->state === 'open') {
            // Check if timeout has elapsed to transition to half-open
            if ($this->openedAt !== null && (microtime(true) - $this->openedAt) >= $this->timeout) {
                $this->state = 'half-open';
                $this->successCount = 0;
                return false;
            }

            return true;
        }

        return false;
    }

    public function isClosed(): bool
    {
        return $this->state === 'closed';
    }

    public function isHalfOpen(): bool
    {
        return $this->state === 'half-open';
    }

    public function recordSuccess(): void
    {
        if ($this->state === 'half-open') {
            $this->successCount++;

            if ($this->successCount >= $this->successThreshold) {
                $this->state = 'closed';
                $this->failureCount = 0;
                $this->successCount = 0;
                $this->openedAt = null;
            }
        } elseif ($this->state === 'closed') {
            $this->failureCount = 0;
        }
    }

    public function recordFailure(): void
    {
        if ($this->state === 'half-open') {
            $this->state = 'open';
            $this->openedAt = microtime(true);
            $this->successCount = 0;
            return;
        }

        $this->failureCount++;

        if ($this->failureCount >= $this->failureThreshold) {
            $this->state = 'open';
            $this->openedAt = microtime(true);
        }
    }

    public function getState(): string
    {
        // Trigger half-open check
        if ($this->state === 'open') {
            $this->isOpen();
        }

        return $this->state;
    }

    public function getFailureCount(): int
    {
        return $this->failureCount;
    }

    public function reset(): void
    {
        $this->state = 'closed';
        $this->failureCount = 0;
        $this->successCount = 0;
        $this->openedAt = null;
    }
}
