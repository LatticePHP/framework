<?php

declare(strict_types=1);

namespace Lattice\Contracts\Workflow;

interface WorkflowEventStoreInterface
{
    public function appendEvent(string $executionId, WorkflowEventInterface $event): void;

    /** @return array<WorkflowEventInterface> */
    public function getEvents(string $executionId): array;

    public function createExecution(string $workflowType, string $workflowId, string $runId, mixed $input): string;

    public function updateExecutionStatus(string $executionId, WorkflowStatus $status, mixed $result = null): void;

    public function getExecution(string $executionId): ?WorkflowExecutionInterface;

    public function findExecutionByWorkflowId(string $workflowId): ?WorkflowExecutionInterface;
}
