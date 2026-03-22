<?php

declare(strict_types=1);

namespace Lattice\Workflow\Runtime;

use Lattice\Contracts\Workflow\WorkflowEventInterface;
use Lattice\Contracts\Workflow\WorkflowEventStoreInterface;
use Lattice\Contracts\Workflow\WorkflowEventType;
use Lattice\Workflow\Event\WorkflowEvent;

final class WorkflowContext
{
    private bool $replaying = false;

    /** @var list<WorkflowEventInterface> Events loaded for replay */
    private array $replayEvents = [];

    /** @var int Current position in replay events */
    private int $replayIndex = 0;

    /** @var int Next sequence number for new events */
    private int $nextSequenceNumber = 1;

    /** @var int Activity counter for generating deterministic activity IDs */
    private int $activityCounter = 0;

    /** @var int Timer counter for generating deterministic timer IDs */
    private int $timerCounter = 0;

    /** @var int Child workflow counter */
    private int $childWorkflowCounter = 0;

    /** @var list<WorkflowEventInterface> Pending signals to deliver */
    private array $pendingSignals = [];

    /** @var int Current simulated time offset in seconds (for testing) */
    private int $timeOffset = 0;

    /** @var ActivityExecutor|null Queue-based executor (set when driver is 'queue') */
    private ?ActivityExecutor $queueActivityExecutor = null;

    /**
     * @param string $activityDriver The activity execution driver: 'sync' or 'queue'
     */
    public function __construct(
        private readonly string $executionId,
        private readonly string $workflowId,
        private readonly string $runId,
        private readonly WorkflowEventStoreInterface $eventStore,
        private readonly ActivityExecutor $activityExecutor,
        private readonly ?WorkflowRuntime $runtime = null,
        private readonly string $activityDriver = 'sync',
    ) {}

    /**
     * Set a queue-based activity executor.
     * Required when activity_driver is 'queue'.
     */
    public function setQueueActivityExecutor(ActivityExecutor $executor): void
    {
        $this->queueActivityExecutor = $executor;
    }

    /**
     * Get the configured activity driver.
     */
    public function getActivityDriver(): string
    {
        return $this->activityDriver;
    }

    /**
     * Resolve the activity executor based on the configured driver.
     */
    private function resolveActivityExecutor(): ActivityExecutor
    {
        if ($this->activityDriver === 'queue' && $this->queueActivityExecutor !== null) {
            return $this->queueActivityExecutor;
        }

        return $this->activityExecutor;
    }

    /**
     * Execute an activity. During replay, returns the recorded result.
     * During live execution, actually executes the activity and records events.
     */
    public function executeActivity(string $activityClass, string $method, mixed ...$args): mixed
    {
        $this->activityCounter++;
        $activityId = 'activity_' . $this->activityCounter;

        if ($this->replaying) {
            try {
                return $this->replayActivity($activityId);
            } catch (ReplayCaughtUpException) {
                // Replay exhausted — fall through to live execution
                // replaying is already set to false by replayActivity
            }
        }

        return $this->executeActivityLive($activityId, $activityClass, $method, $args);
    }

    /**
     * Sleep for a given number of seconds.
     * During replay, skips if timer already fired. During live, records timer events.
     */
    public function sleep(int $seconds): void
    {
        $this->timerCounter++;
        $timerId = 'timer_' . $this->timerCounter;

        if ($this->replaying) {
            try {
                $this->replayTimer($timerId);
                return;
            } catch (ReplayCaughtUpException) {
                // Replay exhausted — fall through to live timer recording
            }
        }

        // Record timer started + fired (in sync mode, timers fire immediately)
        $this->appendEvent(WorkflowEvent::timerStarted(
            $this->nextSequenceNumber(),
            $timerId,
            $seconds,
        ));

        $this->appendEvent(WorkflowEvent::timerFired(
            $this->nextSequenceNumber(),
            $timerId,
        ));
    }

    /**
     * Wait until condition is true.
     */
    public function awaitCondition(callable $condition, int $timeoutSeconds = 0): bool
    {
        // In synchronous execution, evaluate immediately
        return $condition();
    }

    /**
     * Execute a child workflow.
     */
    public function executeChildWorkflow(string $workflowClass, mixed $input = null): mixed
    {
        $this->childWorkflowCounter++;
        $childId = $this->workflowId . '_child_' . $this->childWorkflowCounter;

        if ($this->replaying) {
            try {
                return $this->replayChildWorkflow($childId);
            } catch (ReplayCaughtUpException) {
                // Replay exhausted — fall through to live child workflow execution
            }
        }

        return $this->executeChildWorkflowLive($childId, $workflowClass, $input);
    }

    public function getWorkflowId(): string
    {
        return $this->workflowId;
    }

    public function getRunId(): string
    {
        return $this->runId;
    }

    /**
     * Set the starting sequence number for new events.
     * Used by the runtime to avoid sequence conflicts with events already in the store.
     */
    public function setNextSequenceNumber(int $nextSequenceNumber): void
    {
        $this->nextSequenceNumber = $nextSequenceNumber;
    }

    public function setReplaying(bool $replaying): void
    {
        $this->replaying = $replaying;
    }

    public function isReplaying(): bool
    {
        return $this->replaying;
    }

    /**
     * Load events for replay.
     *
     * @param list<WorkflowEventInterface> $events
     */
    public function loadReplayEvents(array $events): void
    {
        $this->replayEvents = $events;
        $this->replayIndex = 0;

        // Set next sequence number past all existing events
        if (!empty($events)) {
            $maxSeq = 0;
            foreach ($events as $event) {
                if ($event->getSequenceNumber() > $maxSeq) {
                    $maxSeq = $event->getSequenceNumber();
                }
            }
            $this->nextSequenceNumber = $maxSeq + 1;
        }
    }

    /**
     * Collect pending signals from replay events.
     *
     * @return list<WorkflowEventInterface>
     */
    public function collectPendingSignals(): array
    {
        $signals = [];
        foreach ($this->replayEvents as $event) {
            if ($event->getEventType() === WorkflowEventType::SignalReceived) {
                $signals[] = $event;
            }
        }
        return $signals;
    }

    /**
     * Advance simulated time (for testing).
     */
    public function advanceTime(int $seconds): void
    {
        $this->timeOffset += $seconds;
    }

    public function getTimeOffset(): int
    {
        return $this->timeOffset;
    }

    /**
     * Check if replay has caught up to the end of history.
     */
    public function isReplayCaughtUp(): bool
    {
        return $this->replayIndex >= count($this->replayEvents);
    }

    // --- Internal replay methods ---

    private function replayActivity(string $activityId): mixed
    {
        // Find the ActivityScheduled + ActivityCompleted pair
        $scheduledEvent = $this->findNextReplayEvent(WorkflowEventType::ActivityScheduled);
        if ($scheduledEvent === null) {
            // We've caught up to the end of history — switch to live mode
            $this->replaying = false;
            throw new ReplayCaughtUpException();
        }

        $completedEvent = $this->findNextReplayEvent(WorkflowEventType::ActivityCompleted);
        if ($completedEvent === null) {
            // Activity was scheduled but not completed — it might have failed
            $failedEvent = $this->findNextReplayEventOf(
                WorkflowEventType::ActivityFailed,
                WorkflowEventType::ActivityTimedOut,
            );

            if ($failedEvent !== null) {
                $payload = $failedEvent->getPayload();
                if ($failedEvent->getEventType() === WorkflowEventType::ActivityFailed) {
                    throw new ActivityFailedException(
                        $payload['error'] ?? 'Activity failed',
                        $payload['errorClass'] ?? \RuntimeException::class,
                    );
                }
                throw new ActivityTimedOutException('Activity timed out: ' . $activityId);
            }

            // Activity still pending — this shouldn't happen during full replay
            $this->replaying = false;
            throw new ReplayCaughtUpException();
        }

        $payload = $completedEvent->getPayload();

        return $payload['result'];
    }

    private function replayTimer(string $timerId): void
    {
        $startedEvent = $this->findNextReplayEvent(WorkflowEventType::TimerStarted);
        if ($startedEvent === null) {
            $this->replaying = false;
            throw new ReplayCaughtUpException();
        }

        $firedEvent = $this->findNextReplayEvent(WorkflowEventType::TimerFired);
        if ($firedEvent === null) {
            // Timer started but not fired — throw to pause workflow
            throw new TimerPendingException($timerId);
        }
    }

    private function replayChildWorkflow(string $childId): mixed
    {
        $startedEvent = $this->findNextReplayEvent(WorkflowEventType::ChildWorkflowStarted);
        if ($startedEvent === null) {
            $this->replaying = false;
            throw new ReplayCaughtUpException();
        }

        $completedEvent = $this->findNextReplayEvent(WorkflowEventType::ChildWorkflowCompleted);
        if ($completedEvent === null) {
            $failedEvent = $this->findNextReplayEvent(WorkflowEventType::ChildWorkflowFailed);
            if ($failedEvent !== null) {
                throw new \RuntimeException($failedEvent->getPayload()['error'] ?? 'Child workflow failed');
            }
            $this->replaying = false;
            throw new ReplayCaughtUpException();
        }

        return $completedEvent->getPayload()['result'];
    }

    private function findNextReplayEvent(WorkflowEventType $type): ?WorkflowEventInterface
    {
        while ($this->replayIndex < count($this->replayEvents)) {
            $event = $this->replayEvents[$this->replayIndex];
            if ($event->getEventType() === $type) {
                $this->replayIndex++;
                return $event;
            }
            // Skip events we're not looking for (signals, queries, etc.)
            $this->replayIndex++;
        }

        return null;
    }

    private function findNextReplayEventOf(WorkflowEventType ...$types): ?WorkflowEventInterface
    {
        $savedIndex = $this->replayIndex;

        while ($this->replayIndex < count($this->replayEvents)) {
            $event = $this->replayEvents[$this->replayIndex];
            foreach ($types as $type) {
                if ($event->getEventType() === $type) {
                    $this->replayIndex++;
                    return $event;
                }
            }
            $this->replayIndex++;
        }

        // Restore if not found
        $this->replayIndex = $savedIndex;
        return null;
    }

    // --- Internal live execution methods ---

    private function executeActivityLive(string $activityId, string $activityClass, string $method, array $args): mixed
    {
        $executor = $this->resolveActivityExecutor();

        // Record ActivityScheduled
        $this->appendEvent(WorkflowEvent::activityScheduled(
            $this->nextSequenceNumber(),
            $activityId,
            $activityClass,
            $method,
            $args,
        ));

        // When using the queue executor, the job writes ActivityStarted/Completed/Failed.
        // The QueueActivityExecutor dispatches the job and polls for the result.
        if ($executor instanceof QueueActivityExecutor) {
            try {
                return $executor->execute($activityClass, $method, $args);
            } catch (\Throwable $e) {
                throw $e;
            }
        }

        // Sync path: context records all events directly
        $this->appendEvent(WorkflowEvent::activityStarted(
            $this->nextSequenceNumber(),
            $activityId,
        ));

        try {
            $result = $executor->execute($activityClass, $method, $args);

            // Record ActivityCompleted
            $this->appendEvent(WorkflowEvent::activityCompleted(
                $this->nextSequenceNumber(),
                $activityId,
                $result,
            ));

            return $result;
        } catch (\Throwable $e) {
            // Record ActivityFailed
            $this->appendEvent(WorkflowEvent::activityFailed(
                $this->nextSequenceNumber(),
                $activityId,
                $e->getMessage(),
                get_class($e),
            ));

            throw $e;
        }
    }

    private function executeChildWorkflowLive(string $childId, string $workflowClass, mixed $input): mixed
    {
        // Record ChildWorkflowStarted
        $this->appendEvent(WorkflowEvent::childWorkflowStarted(
            $this->nextSequenceNumber(),
            $childId,
            $workflowClass,
        ));

        try {
            if ($this->runtime === null) {
                throw new \RuntimeException('Cannot execute child workflow without runtime');
            }

            $childRunId = bin2hex(random_bytes(16));
            $childExecutionId = $this->runtime->startWorkflow(
                $workflowClass,
                $childId,
                $input,
                null,
            );

            $childExecution = $this->eventStore->getExecution($childExecutionId);
            $result = $childExecution?->getResult();

            // Record ChildWorkflowCompleted
            $this->appendEvent(WorkflowEvent::childWorkflowCompleted(
                $this->nextSequenceNumber(),
                $childId,
                $result,
            ));

            return $result;
        } catch (\Throwable $e) {
            $this->appendEvent(WorkflowEvent::childWorkflowFailed(
                $this->nextSequenceNumber(),
                $childId,
                $e->getMessage(),
            ));

            throw $e;
        }
    }

    private function appendEvent(WorkflowEventInterface $event): void
    {
        $this->eventStore->appendEvent($this->executionId, $event);
    }

    private function nextSequenceNumber(): int
    {
        return $this->nextSequenceNumber++;
    }
}
