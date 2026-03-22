<?php

declare(strict_types=1);

namespace Lattice\Core\Lifecycle;

final class LifecycleManager
{
    /** @var array<string, list<\Closure>> */
    private array $hooks = [
        'preBoot' => [],
        'boot' => [],
        'ready' => [],
        'terminate' => [],
    ];

    /** @var array<string, bool> */
    private array $executed = [];

    public function onPreBoot(\Closure $callback): void
    {
        $this->hooks['preBoot'][] = $callback;
    }

    public function onBoot(\Closure $callback): void
    {
        $this->hooks['boot'][] = $callback;
    }

    public function onReady(\Closure $callback): void
    {
        $this->hooks['ready'][] = $callback;
    }

    public function onTerminate(\Closure $callback): void
    {
        $this->hooks['terminate'][] = $callback;
    }

    public function executePreBoot(): void
    {
        $this->executePhase('preBoot');
    }

    public function executeBoot(): void
    {
        $this->executePhase('boot');
    }

    public function executeReady(): void
    {
        $this->executePhase('ready');
    }

    public function executeTerminate(): void
    {
        $this->executePhase('terminate');
    }

    private function executePhase(string $phase): void
    {
        if (isset($this->executed[$phase])) {
            return;
        }

        $this->executed[$phase] = true;

        foreach ($this->hooks[$phase] as $callback) {
            $callback();
        }
    }
}
