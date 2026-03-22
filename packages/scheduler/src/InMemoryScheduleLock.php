<?php

declare(strict_types=1);

namespace Lattice\Scheduler;

final class InMemoryScheduleLock implements ScheduleLock
{
    /** @var array<string, int> Map of lock name to expiry timestamp */
    private array $locks = [];

    public function acquire(string $name, int $ttl): bool
    {
        $now = time();

        // Check if lock exists and hasn't expired
        if (isset($this->locks[$name]) && $this->locks[$name] > $now) {
            return false;
        }

        $this->locks[$name] = $now + $ttl;

        return true;
    }

    public function release(string $name): void
    {
        unset($this->locks[$name]);
    }
}
