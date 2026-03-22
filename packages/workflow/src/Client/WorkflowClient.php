<?php

declare(strict_types=1);

namespace Lattice\Workflow\Client;

use Lattice\Contracts\Workflow\WorkflowClientInterface;
use Lattice\Contracts\Workflow\WorkflowHandleInterface;
use Lattice\Contracts\Workflow\WorkflowOptionsInterface;
use Lattice\Workflow\Runtime\WorkflowRuntime;
use Lattice\Workflow\WorkflowOptions;

final class WorkflowClient implements WorkflowClientInterface
{
    public function __construct(
        private readonly WorkflowRuntime $runtime,
        private readonly \Lattice\Contracts\Workflow\WorkflowEventStoreInterface $eventStore,
    ) {}

    public function start(
        string $workflowType,
        mixed $input = null,
        ?WorkflowOptionsInterface $options = null,
    ): WorkflowHandleInterface {
        $workflowId = $options?->getWorkflowId() ?? ('wf_' . bin2hex(random_bytes(16)));
        $opts = $options instanceof WorkflowOptions ? $options : null;

        $executionId = $this->runtime->startWorkflow(
            $workflowType,
            $workflowId,
            $input,
            $opts,
        );

        $execution = $this->eventStore->getExecution($executionId);

        return new WorkflowHandle(
            $execution->getWorkflowId(),
            $execution->getRunId(),
            $executionId,
            $this->runtime,
            $this->eventStore,
        );
    }

    public function getHandle(string $workflowId): WorkflowHandleInterface
    {
        $execution = $this->eventStore->findExecutionByWorkflowId($workflowId);
        if ($execution === null) {
            throw new \RuntimeException("No execution found for workflow: {$workflowId}");
        }

        return new WorkflowHandle(
            $execution->getWorkflowId(),
            $execution->getRunId(),
            $execution->getId(),
            $this->runtime,
            $this->eventStore,
        );
    }
}
