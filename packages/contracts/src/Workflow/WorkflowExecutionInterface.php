<?php

declare(strict_types=1);

namespace Lattice\Contracts\Workflow;

interface WorkflowExecutionInterface
{
    public function getId(): string;

    public function getWorkflowType(): string;

    public function getWorkflowId(): string;

    public function getRunId(): string;

    public function getInput(): mixed;

    public function getStatus(): WorkflowStatus;

    public function getResult(): mixed;

    public function getStartedAt(): \DateTimeImmutable;

    public function getCompletedAt(): ?\DateTimeImmutable;

    public function getParentWorkflowId(): ?string;
}
