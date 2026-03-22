<?php

declare(strict_types=1);

namespace Lattice\Workflow\Runtime;

use Lattice\Contracts\Workflow\WorkflowEventStoreInterface;
use Lattice\Contracts\Workflow\WorkflowEventType;
use Lattice\Contracts\Workflow\WorkflowStatus;
use Lattice\Workflow\Attributes\QueryMethod;
use Lattice\Workflow\Attributes\SignalMethod;
use Lattice\Workflow\Attributes\UpdateMethod;
use Lattice\Workflow\Event\WorkflowEvent;
use Lattice\Workflow\Registry\WorkflowRegistry;
use Lattice\Workflow\WorkflowOptions;
use ReflectionClass;
use ReflectionMethod;

final class WorkflowRuntime
{
    public function __construct(
        private readonly WorkflowEventStoreInterface $eventStore,
        private readonly ActivityExecutor $activityExecutor,
        private readonly WorkflowRegistry $registry,
    ) {}

    /**
     * Start a new workflow execution.
     *
     * @return string The execution ID
     */
    public function startWorkflow(
        string $workflowType,
        string $workflowId,
        mixed $input,
        ?WorkflowOptions $options = null,
    ): string {
        $runId = bin2hex(random_bytes(16));

        $executionId = $this->eventStore->createExecution(
            $workflowType,
            $workflowId,
            $runId,
            $input,
        );

        // Record WorkflowStarted event
        $this->eventStore->appendEvent(
            $executionId,
            WorkflowEvent::workflowStarted(1, [
                'workflowType' => $workflowType,
                'workflowId' => $workflowId,
                'input' => $input,
                'options' => $options,
            ]),
        );

        // Execute the workflow (synchronously for now)
        $this->executeWorkflow($executionId);

        return $executionId;
    }

    /**
     * Resume/replay a workflow from its event history.
     * This is the core deterministic replay implementation.
     */
    public function resumeWorkflow(string $executionId): void
    {
        $this->executeWorkflow($executionId);
    }

    /**
     * Send a signal to a running workflow.
     */
    public function signalWorkflow(string $workflowId, string $signalName, mixed $payload = null): void
    {
        $execution = $this->eventStore->findExecutionByWorkflowId($workflowId);
        if ($execution === null) {
            throw new \RuntimeException("No execution found for workflow: {$workflowId}");
        }

        $events = $this->eventStore->getEvents($execution->getId());
        $nextSeq = count($events) + 1;

        // Record the signal event
        $this->eventStore->appendEvent(
            $execution->getId(),
            WorkflowEvent::signalReceived($nextSeq, $signalName, $payload),
        );

        // Re-execute the workflow to process the signal
        if ($execution->getStatus() === WorkflowStatus::Running) {
            $this->executeWorkflow($execution->getId());
        }
    }

    /**
     * Query a workflow's current state.
     * Queries are read-only and do not modify workflow state.
     */
    public function queryWorkflow(string $workflowId, string $queryName, mixed ...$args): mixed
    {
        $execution = $this->eventStore->findExecutionByWorkflowId($workflowId);
        if ($execution === null) {
            throw new \RuntimeException("No execution found for workflow: {$workflowId}");
        }

        // Replay the workflow to rebuild state, then call the query method
        $workflowClass = $this->resolveWorkflowClass($execution->getWorkflowType());
        $instance = new $workflowClass();
        $events = $this->eventStore->getEvents($execution->getId());

        $context = new WorkflowContext(
            $execution->getId(),
            $execution->getWorkflowId(),
            $execution->getRunId(),
            $this->eventStore,
            $this->activityExecutor,
            $this,
        );

        // Replay to rebuild state
        $context->setReplaying(true);
        $context->loadReplayEvents($events);

        // Run the workflow execute method in replay mode to rebuild state
        try {
            $this->invokeWorkflowExecute($instance, $context, $execution->getInput());
        } catch (ReplayCaughtUpException) {
            // Expected — we just needed to rebuild state
        } catch (\Throwable) {
            // Workflow may have failed — that's OK, we still query state
        }

        // Deliver signals AFTER replay to reflect the most recent state
        $signals = $context->collectPendingSignals();
        foreach ($signals as $signalEvent) {
            $signalPayload = $signalEvent->getPayload();
            $method = $this->findSignalMethod($workflowClass, $signalPayload['signalName']);
            if ($method !== null) {
                $method->invoke($instance, $signalPayload['payload'] ?? null);
            }
        }

        // Now call the query method
        $queryMethod = $this->findQueryMethod($workflowClass, $queryName);
        if ($queryMethod === null) {
            throw new \RuntimeException("Query method not found: {$queryName}");
        }

        return $queryMethod->invoke($instance, ...$args);
    }

    /**
     * Send an update to a running workflow.
     */
    public function updateWorkflow(string $workflowId, string $updateName, mixed $payload = null): mixed
    {
        $execution = $this->eventStore->findExecutionByWorkflowId($workflowId);
        if ($execution === null) {
            throw new \RuntimeException("No execution found for workflow: {$workflowId}");
        }

        $events = $this->eventStore->getEvents($execution->getId());
        $nextSeq = count($events) + 1;

        // Record the update event
        $this->eventStore->appendEvent(
            $execution->getId(),
            WorkflowEvent::updateReceived($nextSeq, $updateName, $payload),
        );

        // Replay to rebuild state and call update method
        $workflowClass = $this->resolveWorkflowClass($execution->getWorkflowType());
        $instance = new $workflowClass();

        $context = new WorkflowContext(
            $execution->getId(),
            $execution->getWorkflowId(),
            $execution->getRunId(),
            $this->eventStore,
            $this->activityExecutor,
            $this,
        );

        $context->setReplaying(true);
        $context->loadReplayEvents($this->eventStore->getEvents($execution->getId()));

        // Replay to rebuild state
        try {
            $this->invokeWorkflowExecute($instance, $context, $execution->getInput());
        } catch (ReplayCaughtUpException) {
            // Expected
        } catch (\Throwable) {
            // OK
        }

        // Call update method
        $updateMethod = $this->findUpdateMethod($workflowClass, $updateName);
        if ($updateMethod === null) {
            throw new \RuntimeException("Update method not found: {$updateName}");
        }

        return $updateMethod->invoke($instance, $payload);
    }

    /**
     * Cancel a workflow execution.
     */
    public function cancelWorkflow(string $workflowId): void
    {
        $execution = $this->eventStore->findExecutionByWorkflowId($workflowId);
        if ($execution === null) {
            throw new \RuntimeException("No execution found for workflow: {$workflowId}");
        }

        $events = $this->eventStore->getEvents($execution->getId());
        $nextSeq = count($events) + 1;

        $this->eventStore->appendEvent(
            $execution->getId(),
            WorkflowEvent::workflowCancelled($nextSeq),
        );

        $this->eventStore->updateExecutionStatus($execution->getId(), WorkflowStatus::Cancelled);
    }

    /**
     * Terminate a workflow execution.
     */
    public function terminateWorkflow(string $workflowId, string $reason = ''): void
    {
        $execution = $this->eventStore->findExecutionByWorkflowId($workflowId);
        if ($execution === null) {
            throw new \RuntimeException("No execution found for workflow: {$workflowId}");
        }

        $events = $this->eventStore->getEvents($execution->getId());
        $nextSeq = count($events) + 1;

        $this->eventStore->appendEvent(
            $execution->getId(),
            WorkflowEvent::workflowTerminated($nextSeq, $reason),
        );

        $this->eventStore->updateExecutionStatus($execution->getId(), WorkflowStatus::Terminated);
    }

    // --- Private implementation ---

    private function executeWorkflow(string $executionId): void
    {
        $execution = $this->eventStore->getExecution($executionId);
        if ($execution === null) {
            throw new \RuntimeException("Execution not found: {$executionId}");
        }

        $workflowClass = $this->resolveWorkflowClass($execution->getWorkflowType());
        $instance = new $workflowClass();
        $events = $this->eventStore->getEvents($executionId);

        $context = new WorkflowContext(
            $executionId,
            $execution->getWorkflowId(),
            $execution->getRunId(),
            $this->eventStore,
            $this->activityExecutor,
            $this,
        );

        // Set the starting sequence number past any existing events
        if (!empty($events)) {
            $maxSeq = 0;
            foreach ($events as $event) {
                if ($event->getSequenceNumber() > $maxSeq) {
                    $maxSeq = $event->getSequenceNumber();
                }
            }
            $context->setNextSequenceNumber($maxSeq + 1);
        }

        // If we have existing events beyond WorkflowStarted, go into replay mode
        $hasActivityEvents = false;
        $signalEvents = [];

        foreach ($events as $event) {
            if (
                $event->getEventType() === WorkflowEventType::ActivityScheduled
                || $event->getEventType() === WorkflowEventType::ActivityCompleted
                || $event->getEventType() === WorkflowEventType::TimerStarted
                || $event->getEventType() === WorkflowEventType::ChildWorkflowStarted
            ) {
                $hasActivityEvents = true;
            }
            if ($event->getEventType() === WorkflowEventType::SignalReceived) {
                $signalEvents[] = $event;
            }
        }

        if ($hasActivityEvents) {
            $context->setReplaying(true);
            $context->loadReplayEvents($events);
        }

        try {
            $result = $this->invokeWorkflowExecute($instance, $context, $execution->getInput());

            // Deliver any pending signals
            foreach ($signalEvents as $signalEvent) {
                $signalPayload = $signalEvent->getPayload();
                $method = $this->findSignalMethod($workflowClass, $signalPayload['signalName']);
                if ($method !== null) {
                    $method->invoke($instance, $signalPayload['payload'] ?? null);
                }
            }

            // Workflow completed successfully
            $allEvents = $this->eventStore->getEvents($executionId);
            $nextSeq = count($allEvents) + 1;

            $this->eventStore->appendEvent(
                $executionId,
                WorkflowEvent::workflowCompleted($nextSeq, $result),
            );

            $this->eventStore->updateExecutionStatus(
                $executionId,
                WorkflowStatus::Completed,
                $result,
            );
        } catch (ReplayCaughtUpException) {
            // Replay caught up — workflow needs more events to continue
            // This happens when the workflow is waiting for an activity that hasn't completed yet
        } catch (TimerPendingException) {
            // Timer hasn't fired yet — workflow is paused
        } catch (WorkflowCancelledException) {
            $this->eventStore->updateExecutionStatus($executionId, WorkflowStatus::Cancelled);
        } catch (\Throwable $e) {
            $allEvents = $this->eventStore->getEvents($executionId);
            $nextSeq = count($allEvents) + 1;

            $this->eventStore->appendEvent(
                $executionId,
                WorkflowEvent::workflowFailed($nextSeq, $e->getMessage(), get_class($e)),
            );

            $this->eventStore->updateExecutionStatus($executionId, WorkflowStatus::Failed);
        }
    }

    private function invokeWorkflowExecute(object $instance, WorkflowContext $context, mixed $input): mixed
    {
        if (!method_exists($instance, 'execute')) {
            throw new \RuntimeException('Workflow class must have an execute() method');
        }

        if ($input !== null) {
            return $instance->execute($context, $input);
        }

        return $instance->execute($context);
    }

    private function resolveWorkflowClass(string $workflowType): string
    {
        // First try registry
        if ($this->registry->hasWorkflow($workflowType)) {
            return $this->registry->getWorkflowClass($workflowType);
        }

        // Treat the type as a FQCN
        if (class_exists($workflowType)) {
            return $workflowType;
        }

        throw new \RuntimeException("Cannot resolve workflow type: {$workflowType}");
    }

    private function findSignalMethod(string $workflowClass, string $signalName): ?ReflectionMethod
    {
        $ref = new ReflectionClass($workflowClass);

        foreach ($ref->getMethods() as $method) {
            $attrs = $method->getAttributes(SignalMethod::class);
            if (empty($attrs)) {
                continue;
            }

            /** @var SignalMethod $attr */
            $attr = $attrs[0]->newInstance();
            $name = $attr->name ?? $method->getName();

            if ($name === $signalName) {
                return $method;
            }
        }

        return null;
    }

    private function findQueryMethod(string $workflowClass, string $queryName): ?ReflectionMethod
    {
        $ref = new ReflectionClass($workflowClass);

        foreach ($ref->getMethods() as $method) {
            $attrs = $method->getAttributes(QueryMethod::class);
            if (empty($attrs)) {
                continue;
            }

            /** @var QueryMethod $attr */
            $attr = $attrs[0]->newInstance();
            $name = $attr->name ?? $method->getName();

            if ($name === $queryName) {
                return $method;
            }
        }

        return null;
    }

    private function findUpdateMethod(string $workflowClass, string $updateName): ?ReflectionMethod
    {
        $ref = new ReflectionClass($workflowClass);

        foreach ($ref->getMethods() as $method) {
            $attrs = $method->getAttributes(UpdateMethod::class);
            if (empty($attrs)) {
                continue;
            }

            /** @var UpdateMethod $attr */
            $attr = $attrs[0]->newInstance();
            $name = $attr->name ?? $method->getName();

            if ($name === $updateName) {
                return $method;
            }
        }

        return null;
    }
}
