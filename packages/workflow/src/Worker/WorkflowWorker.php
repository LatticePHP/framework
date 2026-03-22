<?php

declare(strict_types=1);

namespace Lattice\Workflow\Worker;

use Lattice\Contracts\Workflow\WorkflowEventStoreInterface;
use Lattice\Contracts\Workflow\WorkflowStatus;
use Lattice\Workflow\Runtime\WorkflowRuntime;

final class WorkflowWorker
{
    /** @var list<string> Execution IDs that need processing */
    private array $pendingExecutions = [];

    public function __construct(
        private readonly WorkflowRuntime $runtime,
        private readonly WorkflowEventStoreInterface $eventStore,
        private readonly string $taskQueue = 'default',
    ) {}

    /**
     * Enqueue an execution for processing.
     */
    public function enqueue(string $executionId): void
    {
        $this->pendingExecutions[] = $executionId;
    }

    /**
     * Process one pending execution.
     * Returns true if an execution was processed, false if queue was empty.
     */
    public function processOne(): bool
    {
        if (empty($this->pendingExecutions)) {
            return false;
        }

        $executionId = array_shift($this->pendingExecutions);
        $execution = $this->eventStore->getExecution($executionId);

        if ($execution === null || $execution->getStatus() !== WorkflowStatus::Running) {
            return true; // Skip non-running executions
        }

        $this->runtime->resumeWorkflow($executionId);

        return true;
    }

    /**
     * Process all pending executions.
     *
     * @return int Number of executions processed
     */
    public function processAll(): int
    {
        $count = 0;

        while ($this->processOne()) {
            $count++;
        }

        return $count;
    }

    /**
     * Check if there are pending executions.
     */
    public function hasPending(): bool
    {
        return !empty($this->pendingExecutions);
    }
}
