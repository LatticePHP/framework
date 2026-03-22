<?php

declare(strict_types=1);

namespace Lattice\Contracts\Workflow;

interface WorkflowOptionsInterface
{
    public function getWorkflowId(): ?string;

    public function getTaskQueue(): string;

    public function getExecutionTimeout(): ?int;

    public function getRunTimeout(): ?int;

    public function getRetryPolicy(): ?RetryPolicyInterface;
}
