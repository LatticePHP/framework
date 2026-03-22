<?php

declare(strict_types=1);

namespace Lattice\Nightwatch\Watchers;

use Lattice\Nightwatch\Entry;
use Lattice\Nightwatch\Storage\StorageManager;

abstract class AbstractWatcher implements WatcherInterface
{
    protected bool $enabled = true;

    public function __construct(
        protected readonly StorageManager $storage,
    ) {}

    public function handle(Entry $entry): void
    {
        if (!$this->isEnabled() || !$this->shouldRecord($entry)) {
            return;
        }

        $this->storage->store($entry);
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
    }

    public function shouldRecord(Entry $entry): bool
    {
        return true;
    }
}
