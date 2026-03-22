<?php

declare(strict_types=1);

namespace Lattice\Workflow;

use DateTimeImmutable;
use Lattice\Contracts\Workflow\WorkflowExecutionInterface;
use Lattice\Contracts\Workflow\WorkflowStatus;

final class WorkflowExecution implements WorkflowExecutionInterface
{
    private WorkflowStatus $status;
    private mixed $result;
    private ?DateTimeImmutable $completedAt;

    public function __construct(
        private readonly string $id,
        private readonly string $workflowType,
        private readonly string $workflowId,
        private readonly string $runId,
        private readonly mixed $input,
        private readonly DateTimeImmutable $startedAt,
        private readonly ?string $parentWorkflowId = null,
    ) {
        $this->status = WorkflowStatus::Running;
        $this->result = null;
        $this->completedAt = null;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getWorkflowType(): string
    {
        return $this->workflowType;
    }

    public function getWorkflowId(): string
    {
        return $this->workflowId;
    }

    public function getRunId(): string
    {
        return $this->runId;
    }

    public function getInput(): mixed
    {
        return $this->input;
    }

    public function getStatus(): WorkflowStatus
    {
        return $this->status;
    }

    public function getResult(): mixed
    {
        return $this->result;
    }

    public function getStartedAt(): DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function getCompletedAt(): ?DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function getParentWorkflowId(): ?string
    {
        return $this->parentWorkflowId;
    }

    public function setStatus(WorkflowStatus $status): void
    {
        $this->status = $status;
    }

    public function setResult(mixed $result): void
    {
        $this->result = $result;
        $this->completedAt = new DateTimeImmutable();
    }
}
