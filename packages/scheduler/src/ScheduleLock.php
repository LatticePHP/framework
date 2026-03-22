<?php

declare(strict_types=1);

namespace Lattice\Scheduler;

interface ScheduleLock
{
    /**
     * Attempt to acquire a lock for the given task name.
     *
     * @param string $name Task name
     * @param int $ttl Lock time-to-live in seconds
     * @return bool True if the lock was acquired
     */
    public function acquire(string $name, int $ttl): bool;

    /**
     * Release the lock for the given task name.
     */
    public function release(string $name): void;
}
