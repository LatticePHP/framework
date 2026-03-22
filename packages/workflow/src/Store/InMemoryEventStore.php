<?php

declare(strict_types=1);

namespace Lattice\Workflow\Store;

use DateTimeImmutable;
use Lattice\Contracts\Workflow\WorkflowEventInterface;
use Lattice\Contracts\Workflow\WorkflowEventStoreInterface;
use Lattice\Contracts\Workflow\WorkflowExecutionInterface;
use Lattice\Contracts\Workflow\WorkflowStatus;
use Lattice\Workflow\WorkflowExecution;

final class InMemoryEventStore implements WorkflowEventStoreInterface
{
    /** @var array<string, list<WorkflowEventInterface>> */
    private array $events = [];

    /** @var array<string, WorkflowExecution> */
    private array $executions = [];

    private int $executionCounter = 0;

    public function appendEvent(string $executionId, WorkflowEventInterface $event): void
    {
        if (!isset($this->executions[$executionId])) {
            throw new \RuntimeException("Execution not found: {$executionId}");
        }

        $this->events[$executionId][] = $event;
    }

    /** @return array<WorkflowEventInterface> */
    public function getEvents(string $executionId): array
    {
        return $this->events[$executionId] ?? [];
    }

    public function createExecution(string $workflowType, string $workflowId, string $runId, mixed $input): string
    {
        $this->executionCounter++;
        $executionId = 'exec_' . $this->executionCounter;

        $execution = new WorkflowExecution(
            id: $executionId,
            workflowType: $workflowType,
            workflowId: $workflowId,
            runId: $runId,
            input: $input,
            startedAt: new DateTimeImmutable(),
        );

        $this->executions[$executionId] = $execution;
        $this->events[$executionId] = [];

        return $executionId;
    }

    public function updateExecutionStatus(string $executionId, WorkflowStatus $status, mixed $result = null): void
    {
        if (!isset($this->executions[$executionId])) {
            throw new \RuntimeException("Execution not found: {$executionId}");
        }

        $this->executions[$executionId]->setStatus($status);

        if ($result !== null) {
            $this->executions[$executionId]->setResult($result);
        }
    }

    public function getExecution(string $executionId): ?WorkflowExecutionInterface
    {
        return $this->executions[$executionId] ?? null;
    }

    public function findExecutionByWorkflowId(string $workflowId): ?WorkflowExecutionInterface
    {
        foreach ($this->executions as $execution) {
            if ($execution->getWorkflowId() === $workflowId) {
                return $execution;
            }
        }

        return null;
    }
}
