<?php

declare(strict_types=1);

namespace Lattice\Workflow\Runtime;

use Lattice\Contracts\Workflow\ActivityContextInterface;

final class ActivityContext implements ActivityContextInterface
{
    private bool $cancelled = false;

    /**
     * @param callable|null $heartbeatCallback
     */
    public function __construct(
        private readonly string $workflowId,
        private readonly string $activityId,
        private readonly int $attempt,
        private readonly mixed $heartbeatCallback = null,
    ) {}

    public function getWorkflowId(): string
    {
        return $this->workflowId;
    }

    public function getActivityId(): string
    {
        return $this->activityId;
    }

    public function getAttempt(): int
    {
        return $this->attempt;
    }

    public function heartbeat(mixed $details = null): void
    {
        if ($this->heartbeatCallback !== null) {
            ($this->heartbeatCallback)($details);
        }
    }

    public function isCancelled(): bool
    {
        return $this->cancelled;
    }

    public function markCancelled(): void
    {
        $this->cancelled = true;
    }
}
