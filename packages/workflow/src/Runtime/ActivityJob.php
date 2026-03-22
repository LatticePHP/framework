<?php

declare(strict_types=1);

namespace Lattice\Workflow\Runtime;

use Lattice\Contracts\Workflow\WorkflowEventStoreInterface;
use Lattice\Queue\AbstractJob;
use Lattice\Workflow\Event\WorkflowEvent;

final class ActivityJob extends AbstractJob
{
    private ?WorkflowEventStoreInterface $eventStore = null;

    /**
     * @param array<mixed> $args
     */
    public function __construct(
        private readonly string $executionId,
        private readonly string $activityId,
        private readonly string $activityClass,
        private readonly string $method,
        private readonly array $args,
        private readonly int $scheduledSequenceNumber,
        private readonly int $startedSequenceNumber,
    ) {
    }

    public function handle(): void
    {
        $eventStore = $this->getEventStore();

        // Record ActivityStarted
        $eventStore->appendEvent(
            $this->executionId,
            WorkflowEvent::activityStarted($this->startedSequenceNumber, $this->activityId),
        );

        try {
            $instance = new ($this->activityClass)();

            if (!method_exists($instance, $this->method) && !method_exists($instance, '__call')) {
                throw new \RuntimeException(
                    "Method {$this->method} does not exist on {$this->activityClass}",
                );
            }

            $result = $instance->{$this->method}(...$this->args);

            // Record ActivityCompleted — use next sequence after started
            $completedSeq = $this->startedSequenceNumber + 1;
            $eventStore->appendEvent(
                $this->executionId,
                WorkflowEvent::activityCompleted($completedSeq, $this->activityId, $result),
            );
        } catch (\Throwable $e) {
            $this->writeFailed($e);

            throw $e;
        }
    }

    public function failed(\Throwable $e): void
    {
        $this->writeFailed($e);
    }

    public function getExecutionId(): string
    {
        return $this->executionId;
    }

    public function getActivityId(): string
    {
        return $this->activityId;
    }

    public function getActivityClass(): string
    {
        return $this->activityClass;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * @return array<mixed>
     */
    public function getArgs(): array
    {
        return $this->args;
    }

    /**
     * Inject the event store for writing completion/failure events.
     * Called by QueueActivityExecutor before dispatching.
     */
    public function setEventStore(WorkflowEventStoreInterface $eventStore): void
    {
        $this->eventStore = $eventStore;
    }

    private function getEventStore(): WorkflowEventStoreInterface
    {
        if ($this->eventStore === null) {
            throw new \RuntimeException(
                'ActivityJob requires an event store. Call setEventStore() before handling.',
            );
        }

        return $this->eventStore;
    }

    private function writeFailed(\Throwable $e): void
    {
        try {
            $failedSeq = $this->startedSequenceNumber + 1;
            $this->getEventStore()->appendEvent(
                $this->executionId,
                WorkflowEvent::activityFailed(
                    $failedSeq,
                    $this->activityId,
                    $e->getMessage(),
                    get_class($e),
                ),
            );
        } catch (\Throwable) {
            // If we can't write the failure event, swallow — the original exception propagates
        }
    }
}
