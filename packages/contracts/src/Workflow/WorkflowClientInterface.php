<?php

declare(strict_types=1);

namespace Lattice\Contracts\Workflow;

interface WorkflowClientInterface
{
    public function start(string $workflowType, mixed $input = null, ?WorkflowOptionsInterface $options = null): WorkflowHandleInterface;

    public function getHandle(string $workflowId): WorkflowHandleInterface;
}
