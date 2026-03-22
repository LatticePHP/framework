<?php

declare(strict_types=1);

namespace Lattice\Nightwatch\Watchers;

use Lattice\Nightwatch\Entry;
use Lattice\Nightwatch\EntryType;
use Lattice\Nightwatch\Storage\StorageManager;

final class QueryWatcher extends AbstractWatcher
{
    /** @var array<string, int> Track queries for N+1 detection within a batch */
    private array $queryCounters = [];

    public function __construct(
        StorageManager $storage,
        private readonly int $slowThresholdMs = 100,
    ) {
        parent::__construct($storage);
    }

    /**
     * Capture a database query.
     *
     * @param array<string, mixed> $queryData
     */
    public function capture(array $queryData, ?string $batchId = null): Entry
    {
        $sql = $queryData['sql'] ?? '';
        $durationMs = $queryData['duration_ms'] ?? 0;
        $isSlow = $durationMs > $this->slowThresholdMs;

        $normalizedSql = $this->normalizeSql($sql);
        $queryType = $this->detectQueryType($sql);
        $n1Detected = $this->detectN1($normalizedSql, $batchId);

        $entry = new Entry(
            type: EntryType::Query,
            data: [
                'sql' => $sql,
                'bindings' => $queryData['bindings'] ?? [],
                'duration_ms' => $durationMs,
                'connection' => $queryData['connection'] ?? 'default',
                'caller' => $queryData['caller'] ?? null,
                'slow' => $isSlow,
                'query_type' => $queryType,
                'n1_detected' => $n1Detected,
            ],
            tags: $this->buildTags($isSlow, $queryType, $n1Detected),
            batchId: $batchId,
        );

        $this->handle($entry);

        return $entry;
    }

    /**
     * Reset N+1 counters (call at end of request).
     */
    public function resetCounters(): void
    {
        $this->queryCounters = [];
    }

    private function normalizeSql(string $sql): string
    {
        // Replace string literals
        $normalized = preg_replace("/'.+?'/", "'?'", $sql) ?? $sql;
        // Replace numeric literals
        $normalized = preg_replace('/\b\d+\b/', '?', $normalized);
        // Normalize whitespace
        $normalized = preg_replace('/\s+/', ' ', $normalized);

        return trim($normalized);
    }

    private function detectQueryType(string $sql): string
    {
        $sql = ltrim($sql);
        $first = strtoupper(substr($sql, 0, 6));

        return match (true) {
            str_starts_with($first, 'SELECT') => 'SELECT',
            str_starts_with($first, 'INSERT') => 'INSERT',
            str_starts_with($first, 'UPDATE') => 'UPDATE',
            str_starts_with($first, 'DELETE') => 'DELETE',
            default => 'OTHER',
        };
    }

    private function detectN1(string $normalizedSql, ?string $batchId): bool
    {
        $key = ($batchId ?? '_global') . ':' . $normalizedSql;

        if (!isset($this->queryCounters[$key])) {
            $this->queryCounters[$key] = 0;
        }

        $this->queryCounters[$key]++;

        // N+1 pattern: same query executed 3+ times in a single batch
        return $this->queryCounters[$key] >= 3;
    }

    /**
     * @return list<string>
     */
    private function buildTags(bool $isSlow, string $queryType, bool $n1Detected): array
    {
        $tags = ['query_type:' . strtolower($queryType)];

        if ($isSlow) {
            $tags[] = 'slow';
        }

        if ($n1Detected) {
            $tags[] = 'n+1';
        }

        return $tags;
    }
}
