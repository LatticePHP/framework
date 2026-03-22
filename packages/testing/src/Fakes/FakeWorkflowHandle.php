<?php

declare(strict_types=1);

namespace Lattice\Testing\Fakes;

use Lattice\Contracts\Workflow\WorkflowHandleInterface;
use Lattice\Contracts\Workflow\WorkflowStatus;

/**
 * Fake workflow handle for testing.
 */
final class FakeWorkflowHandle implements WorkflowHandleInterface
{
    private WorkflowStatus $status = WorkflowStatus::Running;
    private mixed $result = null;

    public function __construct(
        private readonly string $workflowId,
        private readonly string $runId,
        private readonly FakeWorkflowClient $client,
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
        $this->client->recordSignal($this->workflowId, $signalName, $payload);
    }

    public function query(string $queryName, mixed ...$args): mixed
    {
        return null;
    }

    public function update(string $updateName, mixed $payload = null): mixed
    {
        return null;
    }

    public function cancel(): void
    {
        $this->status = WorkflowStatus::Cancelled;
    }

    public function terminate(string $reason = ''): void
    {
        $this->status = WorkflowStatus::Terminated;
    }

    public function getResult(float $timeoutSeconds = 0): mixed
    {
        return $this->result;
    }

    public function getStatus(): WorkflowStatus
    {
        return $this->status;
    }

    /**
     * Set the result that will be returned by getResult().
     */
    public function setResult(mixed $result): void
    {
        $this->result = $result;
        $this->status = WorkflowStatus::Completed;
    }
}
