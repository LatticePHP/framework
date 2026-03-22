<?php

declare(strict_types=1);

namespace Lattice\Workflow\Testing;

use Lattice\Contracts\Workflow\WorkflowHandleInterface;
use Lattice\Contracts\Workflow\WorkflowOptionsInterface;
use Lattice\Workflow\Client\WorkflowClient;
use Lattice\Workflow\Registry\WorkflowRegistry;
use Lattice\Workflow\Runtime\SyncActivityExecutor;
use Lattice\Workflow\Runtime\WorkflowRuntime;
use Lattice\Workflow\Store\InMemoryEventStore;

final class WorkflowTestEnvironment
{
    private readonly InMemoryEventStore $eventStore;
    private readonly SyncActivityExecutor $activityExecutor;
    private readonly WorkflowRegistry $registry;
    private readonly WorkflowRuntime $runtime;
    private readonly WorkflowClient $client;

    /** @var list<array{type: string, workflowId: string, input: mixed}> */
    private array $startedWorkflows = [];

    /** @var list<array{workflowId: string, signal: string, payload: mixed}> */
    private array $sentSignals = [];

    /** @var list<array{activityClass: string, method: string, args: array}> */
    private array $executedActivities = [];

    public function __construct()
    {
        $this->eventStore = new InMemoryEventStore();
        $this->activityExecutor = new SyncActivityExecutor();
        $this->registry = new WorkflowRegistry();
        $this->runtime = new WorkflowRuntime(
            $this->eventStore,
            $this->activityExecutor,
            $this->registry,
        );
        $this->client = new WorkflowClient($this->runtime, $this->eventStore);
    }

    public function getEventStore(): InMemoryEventStore
    {
        return $this->eventStore;
    }

    public function getActivityExecutor(): SyncActivityExecutor
    {
        return $this->activityExecutor;
    }

    public function getRegistry(): WorkflowRegistry
    {
        return $this->registry;
    }

    public function getRuntime(): WorkflowRuntime
    {
        return $this->runtime;
    }

    public function getClient(): WorkflowClient
    {
        return $this->client;
    }

    /**
     * Register a workflow class.
     */
    public function registerWorkflow(string $class): self
    {
        $this->registry->registerWorkflow($class);
        return $this;
    }

    /**
     * Register an activity class.
     */
    public function registerActivity(string $class): self
    {
        $this->registry->registerActivity($class);
        return $this;
    }

    /**
     * Register a pre-built activity instance (for mocking).
     */
    public function registerActivityInstance(string $class, object $instance): self
    {
        $this->activityExecutor->registerInstance($class, $instance);
        return $this;
    }

    /**
     * Start a workflow and track it.
     */
    public function startWorkflow(
        string $workflowType,
        mixed $input = null,
        ?WorkflowOptionsInterface $options = null,
    ): WorkflowHandleInterface {
        $handle = $this->client->start($workflowType, $input, $options);

        $this->startedWorkflows[] = [
            'type' => $workflowType,
            'workflowId' => $handle->getWorkflowId(),
            'input' => $input,
        ];

        return $handle;
    }

    /**
     * Assert that a workflow of the given type was started.
     */
    public function assertWorkflowStarted(string $workflowType): void
    {
        foreach ($this->startedWorkflows as $wf) {
            if ($wf['type'] === $workflowType) {
                return;
            }
        }

        throw new \RuntimeException("Expected workflow '{$workflowType}' to have been started");
    }

    /**
     * Assert that a signal was sent.
     */
    public function assertSignalSent(string $workflowId, string $signalName): void
    {
        foreach ($this->sentSignals as $signal) {
            if ($signal['workflowId'] === $workflowId && $signal['signal'] === $signalName) {
                return;
            }
        }

        throw new \RuntimeException(
            "Expected signal '{$signalName}' to have been sent to workflow '{$workflowId}'"
        );
    }

    /**
     * Send a signal and track it.
     */
    public function signalWorkflow(string $workflowId, string $signalName, mixed $payload = null): void
    {
        $this->runtime->signalWorkflow($workflowId, $signalName, $payload);

        $this->sentSignals[] = [
            'workflowId' => $workflowId,
            'signal' => $signalName,
            'payload' => $payload,
        ];
    }
}
