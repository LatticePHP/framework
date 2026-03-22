<?php

declare(strict_types=1);

namespace Lattice\Workflow\Runtime;

use Lattice\Contracts\Workflow\WorkflowEventStoreInterface;
use Lattice\Contracts\Workflow\WorkflowEventType;
use Lattice\Queue\Dispatcher;
use Lattice\Workflow\Event\WorkflowEvent;

final class QueueActivityExecutor extends ActivityExecutor
{
    private int $pollIntervalMs;
    private int $timeoutSeconds;

    /**
     * @param Dispatcher $dispatcher Queue dispatcher
     * @param WorkflowEventStoreInterface $eventStore Event store for reading/writing activity events
     * @param string $executionId Current workflow execution ID
     * @param int $pollIntervalMs Milliseconds between event store polls
     * @param int $timeoutSeconds Maximum seconds to wait for activity completion
     * @param (\Closure(ActivityJob): void)|null $jobProcessor Optional callback to process jobs synchronously (for testing)
     */
    public function __construct(
        private readonly Dispatcher $dispatcher,
        private readonly WorkflowEventStoreInterface $eventStore,
        private readonly string $executionId,
        int $pollIntervalMs = 100,
        int $timeoutSeconds = 30,
        private readonly ?\Closure $jobProcessor = null,
    ) {
        $this->pollIntervalMs = $pollIntervalMs;
        $this->timeoutSeconds = $timeoutSeconds;
    }

    protected function doExecute(
        string $activityClass,
        string $method,
        array $args,
        int $attempt,
    ): mixed {
        $events = $this->eventStore->getEvents($this->executionId);

        // Find the most recent ActivityScheduled event (written by WorkflowContext)
        $scheduledEvent = null;
        for ($i = count($events) - 1; $i >= 0; $i--) {
            if ($events[$i]->getEventType() === WorkflowEventType::ActivityScheduled) {
                $scheduledEvent = $events[$i];
                break;
            }
        }

        if ($scheduledEvent === null) {
            throw new \RuntimeException('No ActivityScheduled event found in event store');
        }

        $payload = $scheduledEvent->getPayload();
        $activityId = $payload['activityId'];
        $scheduledSeq = $scheduledEvent->getSequenceNumber();

        // Create the job
        $job = new ActivityJob(
            executionId: $this->executionId,
            activityId: $activityId,
            activityClass: $activityClass,
            method: $method,
            args: $args,
            scheduledSequenceNumber: $scheduledSeq,
            startedSequenceNumber: $scheduledSeq + 1,
        );
        $job->setEventStore($this->eventStore);

        if ($this->jobProcessor !== null) {
            // Test/sync mode: process the job directly to avoid serialization issues
            $this->processJobDirectly($job);
        } else {
            // Production mode: dispatch to queue and poll for result
            $this->dispatcher->dispatch($job);
        }

        // Poll the event store for ActivityCompleted or ActivityFailed
        return $this->pollForResult($activityId);
    }

    protected function waitBeforeRetry(int $seconds): void
    {
        usleep($seconds * 1_000_000);
    }

    /**
     * Process a job directly (for testing or sync mode).
     * The job retains its event store reference since there is no serialization.
     */
    private function processJobDirectly(ActivityJob $job): void
    {
        try {
            ($this->jobProcessor)($job);
        } catch (\Throwable) {
            // Exception handling is done via event store polling — the job writes
            // ActivityFailed before re-throwing, so we can safely ignore the exception here.
        }
    }

    /**
     * Poll the event store until the activity completes or fails.
     */
    private function pollForResult(string $activityId): mixed
    {
        $startTime = time();

        while (true) {
            $events = $this->eventStore->getEvents($this->executionId);

            foreach ($events as $event) {
                $eventPayload = $event->getPayload();
                $eventActivityId = $eventPayload['activityId'] ?? null;

                if ($eventActivityId !== $activityId) {
                    continue;
                }

                if ($event->getEventType() === WorkflowEventType::ActivityCompleted) {
                    return $eventPayload['result'];
                }

                if ($event->getEventType() === WorkflowEventType::ActivityFailed) {
                    throw new ActivityFailedException(
                        $eventPayload['error'] ?? 'Activity failed',
                        $eventPayload['errorClass'] ?? \RuntimeException::class,
                    );
                }
            }

            if ((time() - $startTime) >= $this->timeoutSeconds) {
                throw new ActivityTimedOutException(
                    "Activity {$activityId} timed out after {$this->timeoutSeconds} seconds",
                );
            }

            usleep($this->pollIntervalMs * 1000);
        }
    }
}
