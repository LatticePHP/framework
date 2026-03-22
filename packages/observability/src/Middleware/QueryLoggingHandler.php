<?php

declare(strict_types=1);

namespace Lattice\Observability\Middleware;

use Lattice\Observability\Log;

final class QueryLoggingHandler
{
    /** @var array<int, array{sql: string, bindings: array<mixed>, time_ms: float}> */
    private array $queries = [];

    public function __construct(
        private readonly float $slowQueryThresholdMs = 100.0,
    ) {}

    /**
     * Log a database query.
     *
     * @param array<mixed> $bindings
     */
    public function log(string $sql, array $bindings, float $timeMs): void
    {
        $this->queries[] = [
            'sql' => $sql,
            'bindings' => $bindings,
            'time_ms' => $timeMs,
        ];

        if ($timeMs > $this->slowQueryThresholdMs) {
            Log::warning('Slow query', [
                'sql' => $sql,
                'bindings' => $bindings,
                'time_ms' => round($timeMs, 2),
            ]);
        }
    }

    /**
     * @return array<int, array{sql: string, bindings: array<mixed>, time_ms: float}>
     */
    public function getQueries(): array
    {
        return $this->queries;
    }

    public function getQueryCount(): int
    {
        return count($this->queries);
    }

    public function getTotalTime(): float
    {
        return array_sum(array_column($this->queries, 'time_ms'));
    }

    public function getSlowQueryThresholdMs(): float
    {
        return $this->slowQueryThresholdMs;
    }

    /**
     * Clear all recorded queries.
     */
    public function reset(): void
    {
        $this->queries = [];
    }
}
