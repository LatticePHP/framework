<?php

declare(strict_types=1);

namespace Lattice\Contracts\Workflow;

interface WorkflowHandleInterface
{
    public function getWorkflowId(): string;

    public function getRunId(): string;

    public function signal(string $signalName, mixed $payload = null): void;

    public function query(string $queryName, mixed ...$args): mixed;

    public function update(string $updateName, mixed $payload = null): mixed;

    public function cancel(): void;

    public function terminate(string $reason = ''): void;

    public function getResult(float $timeoutSeconds = 0): mixed;

    public function getStatus(): WorkflowStatus;
}
