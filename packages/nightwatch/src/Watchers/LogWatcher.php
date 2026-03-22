<?php

declare(strict_types=1);

namespace Lattice\Nightwatch\Watchers;

use Lattice\Nightwatch\Entry;
use Lattice\Nightwatch\EntryType;
use Lattice\Nightwatch\Storage\StorageManager;

final class LogWatcher extends AbstractWatcher
{
    private const LEVEL_PRIORITIES = [
        'debug' => 0,
        'info' => 1,
        'notice' => 2,
        'warning' => 3,
        'error' => 4,
        'critical' => 5,
        'alert' => 6,
        'emergency' => 7,
    ];

    public function __construct(
        StorageManager $storage,
        private readonly string $minimumLevel = 'debug',
    ) {
        parent::__construct($storage);
    }

    /**
     * Capture a log entry.
     *
     * @param array<string, mixed> $context
     */
    public function capture(
        string $level,
        string $message,
        array $context = [],
        string $channel = 'default',
        ?string $batchId = null,
    ): ?Entry {
        if (!$this->meetsMinimumLevel($level)) {
            return null;
        }

        $entry = new Entry(
            type: EntryType::Log,
            data: [
                'level' => $level,
                'message' => $message,
                'context' => $context,
                'channel' => $channel,
            ],
            tags: ['level:' . $level, 'channel:' . $channel],
            batchId: $batchId,
        );

        $this->handle($entry);

        return $entry;
    }

    private function meetsMinimumLevel(string $level): bool
    {
        $levelPriority = self::LEVEL_PRIORITIES[strtolower($level)] ?? 0;
        $minimumPriority = self::LEVEL_PRIORITIES[strtolower($this->minimumLevel)] ?? 0;

        return $levelPriority >= $minimumPriority;
    }
}
