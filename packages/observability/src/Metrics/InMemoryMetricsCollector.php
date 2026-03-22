<?php

declare(strict_types=1);

namespace Lattice\Observability\Metrics;

final class InMemoryMetricsCollector implements MetricsCollector
{
    /** @var array<string, array{type: string, value: float, labels: array<string, string>}[]> */
    private array $metrics = [];

    public function counter(string $name, float $value = 1, array $labels = []): void
    {
        if (!isset($this->metrics[$name])) {
            $this->metrics[$name] = ['type' => 'counter', 'entries' => []];
        }

        // Find existing entry with matching labels
        foreach ($this->metrics[$name]['entries'] as &$entry) {
            if ($entry['labels'] === $labels) {
                $entry['value'] += $value;
                return;
            }
        }
        unset($entry);

        $this->metrics[$name]['entries'][] = [
            'value' => $value,
            'labels' => $labels,
        ];
    }

    public function gauge(string $name, float $value, array $labels = []): void
    {
        if (!isset($this->metrics[$name])) {
            $this->metrics[$name] = ['type' => 'gauge', 'entries' => []];
        }

        // Gauge sets (replaces) the value for matching labels
        foreach ($this->metrics[$name]['entries'] as &$entry) {
            if ($entry['labels'] === $labels) {
                $entry['value'] = $value;
                return;
            }
        }
        unset($entry);

        $this->metrics[$name]['entries'][] = [
            'value' => $value,
            'labels' => $labels,
        ];
    }

    public function histogram(string $name, float $value, array $labels = []): void
    {
        if (!isset($this->metrics[$name])) {
            $this->metrics[$name] = ['type' => 'histogram', 'entries' => []];
        }

        // Find existing entry with matching labels
        foreach ($this->metrics[$name]['entries'] as &$entry) {
            if ($entry['labels'] === $labels) {
                $entry['values'][] = $value;
                $entry['count']++;
                $entry['sum'] += $value;
                return;
            }
        }
        unset($entry);

        $this->metrics[$name]['entries'][] = [
            'values' => [$value],
            'count' => 1,
            'sum' => $value,
            'labels' => $labels,
        ];
    }

    public function getMetrics(): array
    {
        return $this->metrics;
    }
}
