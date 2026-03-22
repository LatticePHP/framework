<?php

declare(strict_types=1);

namespace Lattice\Nightwatch\Watchers;

use Lattice\Nightwatch\Entry;

interface WatcherInterface
{
    /**
     * Handle an incoming entry for this watcher.
     */
    public function handle(Entry $entry): void;

    /**
     * Whether this watcher is currently enabled.
     */
    public function isEnabled(): bool;

    /**
     * Determine if the given entry should be recorded.
     */
    public function shouldRecord(Entry $entry): bool;
}
