<?php

declare(strict_types=1);

namespace Lattice\Observability\Metrics;

interface MetricsCollector
{
    public function counter(string $name, float $value = 1, array $labels = []): void;

    public function gauge(string $name, float $value, array $labels = []): void;

    public function histogram(string $name, float $value, array $labels = []): void;

    /**
     * @return array<string, mixed>
     */
    public function getMetrics(): array;
}
