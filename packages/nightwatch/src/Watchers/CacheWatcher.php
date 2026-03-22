<?php

declare(strict_types=1);

namespace Lattice\Nightwatch\Watchers;

use Lattice\Nightwatch\Entry;
use Lattice\Nightwatch\EntryType;
use Lattice\Nightwatch\Storage\StorageManager;

final class CacheWatcher extends AbstractWatcher
{
    /** @var list<string> */
    private readonly array $ignoredKeyPatterns;

    /**
     * @param list<string> $ignoredKeyPatterns
     */
    public function __construct(
        StorageManager $storage,
        array $ignoredKeyPatterns = [],
    ) {
        parent::__construct($storage);
        $this->ignoredKeyPatterns = $ignoredKeyPatterns;
    }

    /**
     * Capture a cache operation.
     *
     * @param 'hit'|'miss'|'write'|'forget' $operation
     */
    public function capture(
        string $operation,
        string $key,
        ?int $ttl = null,
        ?int $valueSize = null,
        string $store = 'default',
        float $durationMs = 0,
        ?string $batchId = null,
    ): ?Entry {
        if ($this->isIgnored($key)) {
            return null;
        }

        $entry = new Entry(
            type: EntryType::Cache,
            data: [
                'operation' => $operation,
                'key' => $key,
                'ttl' => $ttl,
                'value_size' => $valueSize,
                'store' => $store,
                'duration_ms' => $durationMs,
            ],
            tags: ['operation:' . $operation, 'store:' . $store],
            batchId: $batchId,
        );

        $this->handle($entry);

        return $entry;
    }

    private function isIgnored(string $key): bool
    {
        foreach ($this->ignoredKeyPatterns as $pattern) {
            if (fnmatch($pattern, $key)) {
                return true;
            }
        }

        return false;
    }
}
