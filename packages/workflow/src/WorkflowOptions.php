<?php

declare(strict_types=1);

namespace Lattice\Workflow;

use Lattice\Contracts\Workflow\RetryPolicyInterface;
use Lattice\Contracts\Workflow\WorkflowOptionsInterface;

final class WorkflowOptions implements WorkflowOptionsInterface
{
    public function __construct(
        private readonly ?string $workflowId = null,
        private readonly string $taskQueue = 'default',
        private readonly ?int $executionTimeout = null,
        private readonly ?int $runTimeout = null,
        private readonly ?RetryPolicyInterface $retryPolicy = null,
    ) {}

    public function getWorkflowId(): ?string
    {
        return $this->workflowId;
    }

    public function getTaskQueue(): string
    {
        return $this->taskQueue;
    }

    public function getExecutionTimeout(): ?int
    {
        return $this->executionTimeout;
    }

    public function getRunTimeout(): ?int
    {
        return $this->runTimeout;
    }

    public function getRetryPolicy(): ?RetryPolicyInterface
    {
        return $this->retryPolicy;
    }
}
