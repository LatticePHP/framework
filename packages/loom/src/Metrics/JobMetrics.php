<?php

declare(strict_types=1);

namespace Lattice\Loom\Metrics;

final readonly class JobMetrics
{
    public function __construct(
        public int $totalProcessed,
        public int $totalFailed,
        public int $processedLastHour,
        public int $failedLastHour,
        public float $throughputPerMinute,
        public float $averageRuntimeMs,
        public float $averageWaitMs,
        public int $activeWorkers,
        /** @var array<string, int> queue name => pending count */
        public array $queueSizes,
    ) {}

    /**
     * @return array{total_processed: int, total_failed: int, processed_last_hour: int, failed_last_hour: int, throughput_per_minute: float, avg_runtime_ms: float, avg_wait_ms: float, active_workers: int, queue_sizes: array<string, int>}
     */
    public function toArray(): array
    {
        return [
            'total_processed' => $this->totalProcessed,
            'total_failed' => $this->totalFailed,
            'processed_last_hour' => $this->processedLastHour,
            'failed_last_hour' => $this->failedLastHour,
            'throughput_per_minute' => $this->throughputPerMinute,
            'avg_runtime_ms' => $this->averageRuntimeMs,
            'avg_wait_ms' => $this->averageWaitMs,
            'active_workers' => $this->activeWorkers,
            'queue_sizes' => $this->queueSizes,
        ];
    }
}
