<?php

declare(strict_types=1);

namespace Lattice\Workflow\Event;

use DateTimeImmutable;
use Lattice\Contracts\Workflow\WorkflowEventInterface;
use Lattice\Contracts\Workflow\WorkflowEventType;

final class WorkflowEvent implements WorkflowEventInterface
{
    public function __construct(
        private readonly WorkflowEventType $eventType,
        private readonly int $sequenceNumber,
        private readonly mixed $payload,
        private readonly DateTimeImmutable $timestamp,
    ) {}

    public function getEventType(): WorkflowEventType
    {
        return $this->eventType;
    }

    public function getSequenceNumber(): int
    {
        return $this->sequenceNumber;
    }

    public function getPayload(): mixed
    {
        return $this->payload;
    }

    public function getTimestamp(): DateTimeImmutable
    {
        return $this->timestamp;
    }

    public static function workflowStarted(int $sequenceNumber, array $payload): self
    {
        return new self(
            WorkflowEventType::WorkflowStarted,
            $sequenceNumber,
            $payload,
            new DateTimeImmutable(),
        );
    }

    public static function workflowCompleted(int $sequenceNumber, mixed $result): self
    {
        return new self(
            WorkflowEventType::WorkflowCompleted,
            $sequenceNumber,
            ['result' => $result],
            new DateTimeImmutable(),
        );
    }

    public static function workflowFailed(int $sequenceNumber, string $error, string $errorClass): self
    {
        return new self(
            WorkflowEventType::WorkflowFailed,
            $sequenceNumber,
            ['error' => $error, 'errorClass' => $errorClass],
            new DateTimeImmutable(),
        );
    }

    public static function workflowCancelled(int $sequenceNumber): self
    {
        return new self(
            WorkflowEventType::WorkflowCancelled,
            $sequenceNumber,
            [],
            new DateTimeImmutable(),
        );
    }

    public static function workflowTerminated(int $sequenceNumber, string $reason): self
    {
        return new self(
            WorkflowEventType::WorkflowTerminated,
            $sequenceNumber,
            ['reason' => $reason],
            new DateTimeImmutable(),
        );
    }

    public static function activityScheduled(int $sequenceNumber, string $activityId, string $activityClass, string $method, array $args): self
    {
        return new self(
            WorkflowEventType::ActivityScheduled,
            $sequenceNumber,
            [
                'activityId' => $activityId,
                'activityClass' => $activityClass,
                'method' => $method,
                'args' => $args,
            ],
            new DateTimeImmutable(),
        );
    }

    public static function activityStarted(int $sequenceNumber, string $activityId): self
    {
        return new self(
            WorkflowEventType::ActivityStarted,
            $sequenceNumber,
            ['activityId' => $activityId],
            new DateTimeImmutable(),
        );
    }

    public static function activityCompleted(int $sequenceNumber, string $activityId, mixed $result): self
    {
        return new self(
            WorkflowEventType::ActivityCompleted,
            $sequenceNumber,
            ['activityId' => $activityId, 'result' => $result],
            new DateTimeImmutable(),
        );
    }

    public static function activityFailed(int $sequenceNumber, string $activityId, string $error, string $errorClass): self
    {
        return new self(
            WorkflowEventType::ActivityFailed,
            $sequenceNumber,
            [
                'activityId' => $activityId,
                'error' => $error,
                'errorClass' => $errorClass,
            ],
            new DateTimeImmutable(),
        );
    }

    public static function activityTimedOut(int $sequenceNumber, string $activityId): self
    {
        return new self(
            WorkflowEventType::ActivityTimedOut,
            $sequenceNumber,
            ['activityId' => $activityId],
            new DateTimeImmutable(),
        );
    }

    public static function timerStarted(int $sequenceNumber, string $timerId, int $durationSeconds): self
    {
        return new self(
            WorkflowEventType::TimerStarted,
            $sequenceNumber,
            ['timerId' => $timerId, 'durationSeconds' => $durationSeconds],
            new DateTimeImmutable(),
        );
    }

    public static function timerFired(int $sequenceNumber, string $timerId): self
    {
        return new self(
            WorkflowEventType::TimerFired,
            $sequenceNumber,
            ['timerId' => $timerId],
            new DateTimeImmutable(),
        );
    }

    public static function timerCancelled(int $sequenceNumber, string $timerId): self
    {
        return new self(
            WorkflowEventType::TimerCancelled,
            $sequenceNumber,
            ['timerId' => $timerId],
            new DateTimeImmutable(),
        );
    }

    public static function signalReceived(int $sequenceNumber, string $signalName, mixed $payload): self
    {
        return new self(
            WorkflowEventType::SignalReceived,
            $sequenceNumber,
            ['signalName' => $signalName, 'payload' => $payload],
            new DateTimeImmutable(),
        );
    }

    public static function queryReceived(int $sequenceNumber, string $queryName, array $args): self
    {
        return new self(
            WorkflowEventType::QueryReceived,
            $sequenceNumber,
            ['queryName' => $queryName, 'args' => $args],
            new DateTimeImmutable(),
        );
    }

    public static function updateReceived(int $sequenceNumber, string $updateName, mixed $payload): self
    {
        return new self(
            WorkflowEventType::UpdateReceived,
            $sequenceNumber,
            ['updateName' => $updateName, 'payload' => $payload],
            new DateTimeImmutable(),
        );
    }

    public static function childWorkflowStarted(int $sequenceNumber, string $childWorkflowId, string $workflowType): self
    {
        return new self(
            WorkflowEventType::ChildWorkflowStarted,
            $sequenceNumber,
            ['childWorkflowId' => $childWorkflowId, 'workflowType' => $workflowType],
            new DateTimeImmutable(),
        );
    }

    public static function childWorkflowCompleted(int $sequenceNumber, string $childWorkflowId, mixed $result): self
    {
        return new self(
            WorkflowEventType::ChildWorkflowCompleted,
            $sequenceNumber,
            ['childWorkflowId' => $childWorkflowId, 'result' => $result],
            new DateTimeImmutable(),
        );
    }

    public static function childWorkflowFailed(int $sequenceNumber, string $childWorkflowId, string $error): self
    {
        return new self(
            WorkflowEventType::ChildWorkflowFailed,
            $sequenceNumber,
            ['childWorkflowId' => $childWorkflowId, 'error' => $error],
            new DateTimeImmutable(),
        );
    }
}
