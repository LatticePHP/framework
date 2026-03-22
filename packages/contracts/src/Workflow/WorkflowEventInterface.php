<?php

declare(strict_types=1);

namespace Lattice\Contracts\Workflow;

interface WorkflowEventInterface
{
    public function getEventType(): WorkflowEventType;

    public function getSequenceNumber(): int;

    public function getPayload(): mixed;

    public function getTimestamp(): \DateTimeImmutable;
}
