<?php

declare(strict_types=1);

namespace Lattice\Nightwatch\Recorders;

final class RequestRecorder extends AbstractRecorder
{
    /** @var array<string, list<float>> Latencies grouped by endpoint */
    private array $latencies = [];

    /** @var array<string, array<int, int>> Status code counts grouped by endpoint */
    private array $statusCounts = [];

    /** @var int Total request count */
    private int $requestCount = 0;

    /**
     * @param array<string, mixed> $data Expected keys: endpoint, duration_ms, status
     */
    public function record(array $data): void
    {
        if (!$this->isEnabled() || !$this->shouldSample()) {
            return;
        }

        $endpoint = $data['endpoint'] ?? 'unknown';
        $durationMs = (float) ($data['duration_ms'] ?? 0);
        $status = (int) ($data['status'] ?? 200);

        $this->latencies[$endpoint][] = $durationMs;
        $this->statusCounts[$endpoint][$status] = ($this->statusCounts[$endpoint][$status] ?? 0) + 1;
        $this->requestCount++;
    }

    /**
     * @return array<string, mixed>
     */
    public function getMetrics(): array
    {
        $endpoints = [];

        foreach ($this->latencies as $endpoint => $latencies) {
            sort($latencies);
            $count = count($latencies);

            $endpoints[$endpoint] = [
                'count' => $count,
                'p50' => $this->percentile($latencies, 50),
                'p95' => $this->percentile($latencies, 95),
                'p99' => $this->percentile($latencies, 99),
                'avg' => $count > 0 ? array_sum($latencies) / $count : 0,
                'min' => $count > 0 ? min($latencies) : 0,
                'max' => $count > 0 ? max($latencies) : 0,
                'status_codes' => $this->statusCounts[$endpoint] ?? [],
            ];
        }

        return [
            'total_requests' => $this->requestCount,
            'endpoints' => $endpoints,
        ];
    }

    public function reset(): void
    {
        $this->latencies = [];
        $this->statusCounts = [];
        $this->requestCount = 0;
    }

    /**
     * Calculate percentile from sorted array.
     *
     * @param list<float> $sorted
     */
    private function percentile(array $sorted, int $percentile): float
    {
        $count = count($sorted);

        if ($count === 0) {
            return 0.0;
        }

        if ($count === 1) {
            return $sorted[0];
        }

        $index = ($percentile / 100) * ($count - 1);
        $lower = (int) floor($index);
        $upper = (int) ceil($index);
        $fraction = $index - $lower;

        if ($lower === $upper) {
            return $sorted[$lower];
        }

        return $sorted[$lower] + $fraction * ($sorted[$upper] - $sorted[$lower]);
    }
}
