<?php

declare(strict_types=1);

namespace Lattice\Nightwatch\Recorders;

interface RecorderInterface
{
    /**
     * Record an aggregated metric.
     *
     * @param array<string, mixed> $data
     */
    public function record(array $data): void;

    /**
     * Whether this recorder is enabled.
     */
    public function isEnabled(): bool;

    /**
     * Get the current aggregated metrics.
     *
     * @return array<string, mixed>
     */
    public function getMetrics(): array;

    /**
     * Reset the aggregated metrics.
     */
    public function reset(): void;
}
