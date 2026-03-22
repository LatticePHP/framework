<?php

declare(strict_types=1);

namespace Lattice\Workflow\Runtime;

final class SyncActivityExecutor extends ActivityExecutor
{
    /** @var array<string, object> Pre-created activity instances (for DI) */
    private array $instances = [];

    public function registerInstance(string $activityClass, object $instance): void
    {
        $this->instances[$activityClass] = $instance;
    }

    protected function doExecute(
        string $activityClass,
        string $method,
        array $args,
        int $attempt,
    ): mixed {
        $instance = $this->instances[$activityClass] ?? new $activityClass();

        if (!method_exists($instance, $method) && !method_exists($instance, '__call')) {
            throw new \RuntimeException("Method {$method} does not exist on {$activityClass}");
        }

        return $instance->$method(...$args);
    }

    protected function waitBeforeRetry(int $seconds): void
    {
        // No waiting in sync executor — immediate retry for tests
    }
}
