<?php

declare(strict_types=1);

namespace Lattice\Testing\Fakes;

use Lattice\Contracts\Workflow\WorkflowClientInterface;
use Lattice\Contracts\Workflow\WorkflowHandleInterface;
use Lattice\Contracts\Workflow\WorkflowOptionsInterface;
use PHPUnit\Framework\Assert;

/**
 * Captures workflow operations for assertion in tests.
 */
final class FakeWorkflowClient implements WorkflowClientInterface
{
    /** @var list<array{type: string, input: mixed, options: ?WorkflowOptionsInterface}> */
    private array $startedWorkflows = [];

    /** @var list<array{workflowId: string, signalName: string, payload: mixed}> */
    private array $sentSignals = [];

    public function start(string $workflowType, mixed $input = null, ?WorkflowOptionsInterface $options = null): WorkflowHandleInterface
    {
        $workflowId = 'fake-' . count($this->startedWorkflows) + 1;

        $this->startedWorkflows[] = [
            'type' => $workflowType,
            'input' => $input,
            'options' => $options,
        ];

        return new FakeWorkflowHandle($workflowId, 'run-1', $this);
    }

    public function getHandle(string $workflowId): WorkflowHandleInterface
    {
        return new FakeWorkflowHandle($workflowId, 'run-1', $this);
    }

    /**
     * Record that a signal was sent to a workflow.
     */
    public function recordSignal(string $workflowId, string $signalName, mixed $payload = null): void
    {
        $this->sentSignals[] = [
            'workflowId' => $workflowId,
            'signalName' => $signalName,
            'payload' => $payload,
        ];
    }

    /**
     * Assert that a workflow of the given type was started.
     */
    public function assertWorkflowStarted(string $type): void
    {
        $found = false;

        foreach ($this->startedWorkflows as $workflow) {
            if ($workflow['type'] === $type) {
                $found = true;
                break;
            }
        }

        Assert::assertTrue($found, sprintf('Expected workflow [%s] was not started.', $type));
    }

    /**
     * Assert that a signal was sent to the specified workflow.
     */
    public function assertSignalSent(string $workflowId, string $signalName): void
    {
        $found = false;

        foreach ($this->sentSignals as $signal) {
            if ($signal['workflowId'] === $workflowId && $signal['signalName'] === $signalName) {
                $found = true;
                break;
            }
        }

        Assert::assertTrue(
            $found,
            sprintf('Expected signal [%s] was not sent to workflow [%s].', $signalName, $workflowId)
        );
    }
}
