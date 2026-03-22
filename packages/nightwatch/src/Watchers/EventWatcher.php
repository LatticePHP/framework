<?php

declare(strict_types=1);

namespace Lattice\Nightwatch\Watchers;

use Lattice\Nightwatch\Entry;
use Lattice\Nightwatch\EntryType;
use Lattice\Nightwatch\Storage\StorageManager;

final class EventWatcher extends AbstractWatcher
{
    /** @var list<string> */
    private readonly array $ignoredEvents;

    /**
     * @param list<string> $ignoredEvents
     */
    public function __construct(
        StorageManager $storage,
        array $ignoredEvents = [],
    ) {
        parent::__construct($storage);
        $this->ignoredEvents = $ignoredEvents;
    }

    /**
     * Capture a dispatched event.
     *
     * @param array<string, mixed> $payload
     * @param list<string> $listeners
     */
    public function capture(
        string $eventClass,
        array $payload = [],
        array $listeners = [],
        bool $broadcast = false,
        ?string $batchId = null,
    ): ?Entry {
        if ($this->isIgnored($eventClass)) {
            return null;
        }

        $entry = new Entry(
            type: EntryType::Event,
            data: [
                'event_class' => $eventClass,
                'payload' => $payload,
                'listeners' => $listeners,
                'broadcast' => $broadcast,
            ],
            tags: ['event:' . $this->shortClassName($eventClass)],
            batchId: $batchId,
        );

        $this->handle($entry);

        return $entry;
    }

    private function isIgnored(string $eventClass): bool
    {
        foreach ($this->ignoredEvents as $ignored) {
            if ($eventClass === $ignored || is_a($eventClass, $ignored, true)) {
                return true;
            }
        }

        return false;
    }

    private function shortClassName(string $class): string
    {
        $parts = explode('\\', $class);

        return end($parts);
    }
}
