<?php

declare(strict_types=1);

namespace Lattice\RoadRunner;

final class WorkerLifecycle
{
    /** @var list<callable> */
    private array $startupCallbacks = [];

    /** @var list<callable> */
    private array $shutdownCallbacks = [];

    /** @var list<callable> */
    private array $requestCallbacks = [];

    /** @var list<callable> */
    private array $drainCallbacks = [];

    public function onStartup(callable $callback): void
    {
        $this->startupCallbacks[] = $callback;
    }

    public function onShutdown(callable $callback): void
    {
        $this->shutdownCallbacks[] = $callback;
    }

    public function onRequest(callable $callback): void
    {
        $this->requestCallbacks[] = $callback;
    }

    public function onDrain(callable $callback): void
    {
        $this->drainCallbacks[] = $callback;
    }

    public function triggerStartup(): void
    {
        foreach ($this->startupCallbacks as $callback) {
            $callback();
        }
    }

    public function triggerShutdown(): void
    {
        foreach ($this->shutdownCallbacks as $callback) {
            $callback();
        }
    }

    public function triggerRequest(): void
    {
        foreach ($this->requestCallbacks as $callback) {
            $callback();
        }
    }

    public function triggerDrain(): void
    {
        foreach ($this->drainCallbacks as $callback) {
            $callback();
        }
    }
}
