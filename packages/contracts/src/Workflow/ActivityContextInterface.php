<?php

declare(strict_types=1);

namespace Lattice\Contracts\Workflow;

interface ActivityContextInterface
{
    public function getWorkflowId(): string;

    public function getActivityId(): string;

    public function getAttempt(): int;

    public function heartbeat(mixed $details = null): void;
}
