<?php

declare(strict_types=1);

namespace Lattice\Contracts\Workflow;

interface RetryPolicyInterface
{
    public function getMaxAttempts(): int;

    public function getInitialInterval(): int;

    public function getBackoffCoefficient(): float;

    public function getMaxInterval(): ?int;

    /** @return array<class-string<\Throwable>> */
    public function getNonRetryableExceptions(): array;
}
