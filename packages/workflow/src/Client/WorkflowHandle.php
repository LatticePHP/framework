<?php

declare(strict_types=1);

namespace Lattice\Workflow\Client;

use Lattice\Contracts\Workflow\WorkflowEventStoreInterface;
use Lattice\Contracts\Workflow\WorkflowHandleInterface;
use Lattice\Contracts\Workflow\WorkflowStatus;
use Lattice\Workflow\Runtime\WorkflowRuntime;

final class WorkflowHandle implements WorkflowHandleInterface
{
    public function __construct(
        private readonly string $workflowId,
        private readonly string $runId,
        private readonly string $executionId,
        private readonly WorkflowRuntime $runtime,
        private readonly WorkflowEventStoreInterface $eventStore,
    ) {}

    public function getWorkflowId(): string
    {
        return $this->workflowId;
    }

    public function getRunId(): string
    {
        return $this->runId;
    }

    public function signal(string $signalName, mixed $payload = null): void
    {
        $this->runtime->signalWorkflow($this->workflowId, $signalName, $payload);
    }

    public function query(string $queryName, mixed ...$args): mixed
    {
        return $this->runtime->queryWorkflow($this->workflowId, $queryName, ...$args);
    }

    public function update(string $updateName, mixed $payload = null): mixed
    {
        return $this->runtime->updateWorkflow($this->workflowId, $updateName, $payload);
    }

    public function cancel(): void
    {
        $this->runtime->cancelWorkflow($this->workflowId);
    }

    public function terminate(string $reason = ''): void
    {
        $this->runtime->terminateWorkflow($this->workflowId, $reason);
    }

    public function getResult(float $timeoutSeconds = 0): mixed
    {
        $execution = $this->eventStore->getExecution($this->executionId);

        if ($execution === null) {
            throw new \RuntimeException("Execution not found: {$this->executionId}");
        }

        if ($execution->getStatus() === WorkflowStatus::Running) {
            // In a real implementation, we'd poll/wait. For sync execution, it should already be done.
            throw new \RuntimeException('Workflow is still running');
        }

        if ($execution->getStatus() === WorkflowStatus::Failed) {
            throw new \RuntimeException('Workflow execution failed');
        }

        if ($execution->getStatus() === WorkflowStatus::Cancelled) {
            throw new \RuntimeException('Workflow was cancelled');
        }

        if ($execution->getStatus() === WorkflowStatus::Terminated) {
            throw new \RuntimeException('Workflow was terminated');
        }

        return $execution->getResult();
    }

    public function getStatus(): WorkflowStatus
    {
        $execution = $this->eventStore->getExecution($this->executionId);

        if ($execution === null) {
            throw new \RuntimeException("Execution not found: {$this->executionId}");
        }

        return $execution->getStatus();
    }
}
