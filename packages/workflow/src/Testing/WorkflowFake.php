<?php

declare(strict_types=1);

namespace Lattice\Workflow\Testing;

/**
 * Tracks workflow executions for assertions in tests.
 */
final class WorkflowFake
{
    /** @var list<array{type: string, workflowId: string, input: mixed}> */
    private array $startedWorkflows = [];

    /** @var list<array{workflowId: string, signal: string, payload: mixed}> */
    private array $sentSignals = [];

    /** @var list<array{activityClass: string, method: string, args: array}> */
    private array $executedActivities = [];

    public function recordWorkflowStarted(string $type, string $workflowId, mixed $input = null): void
    {
        $this->startedWorkflows[] = [
            'type' => $type,
            'workflowId' => $workflowId,
            'input' => $input,
        ];
    }

    public function recordSignalSent(string $workflowId, string $signal, mixed $payload = null): void
    {
        $this->sentSignals[] = [
            'workflowId' => $workflowId,
            'signal' => $signal,
            'payload' => $payload,
        ];
    }

    public function recordActivityExecuted(string $activityClass, string $method, array $args = []): void
    {
        $this->executedActivities[] = [
            'activityClass' => $activityClass,
            'method' => $method,
            'args' => $args,
        ];
    }

    public function assertWorkflowStarted(string $type): void
    {
        foreach ($this->startedWorkflows as $wf) {
            if ($wf['type'] === $type) {
                return;
            }
        }

        throw new \RuntimeException("Expected workflow '{$type}' to have been started");
    }

    public function assertWorkflowNotStarted(string $type): void
    {
        foreach ($this->startedWorkflows as $wf) {
            if ($wf['type'] === $type) {
                throw new \RuntimeException("Expected workflow '{$type}' to NOT have been started");
            }
        }
    }

    public function assertSignalSent(string $workflowId, string $signal): void
    {
        foreach ($this->sentSignals as $s) {
            if ($s['workflowId'] === $workflowId && $s['signal'] === $signal) {
                return;
            }
        }

        throw new \RuntimeException(
            "Expected signal '{$signal}' to have been sent to workflow '{$workflowId}'"
        );
    }

    public function assertActivityExecuted(string $activityClass, string $method): void
    {
        foreach ($this->executedActivities as $a) {
            if ($a['activityClass'] === $activityClass && $a['method'] === $method) {
                return;
            }
        }

        throw new \RuntimeException(
            "Expected activity '{$activityClass}::{$method}' to have been executed"
        );
    }

    /** @return list<array{type: string, workflowId: string, input: mixed}> */
    public function getStartedWorkflows(): array
    {
        return $this->startedWorkflows;
    }

    /** @return list<array{workflowId: string, signal: string, payload: mixed}> */
    public function getSentSignals(): array
    {
        return $this->sentSignals;
    }

    /** @return list<array{activityClass: string, method: string, args: array}> */
    public function getExecutedActivities(): array
    {
        return $this->executedActivities;
    }
}
