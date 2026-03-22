<?php

declare(strict_types=1);

namespace Lattice\Loom\Metrics;

/**
 * In-memory metrics store. Records job lifecycle metrics and provides
 * retrieval for dashboard stats, recent jobs, and worker tracking.
 *
 * Redis implementation can replace this for production use.
 */
final class MetricsStore
{
    /** @var array{processed: int, failed: int, total_runtime: float, total_wait: float} */
    private array $counters = [
        'processed' => 0,
        'failed' => 0,
        'total_runtime' => 0.0,
        'total_wait' => 0.0,
    ];

    /** @var array<int, array{queue: string, type: string, timestamp: int}> */
    private array $throughputTimeline = [];

    /** @var array<int, array{queue: string, runtime_ms: float, timestamp: int}> */
    private array $runtimeTimeline = [];

    /** @var array<string, array<string, mixed>[]> queue => recent jobs */
    private array $recentJobs = [];

    /** @var array<string, array{queue: string, pid: int, memory_mb: float, last_heartbeat: int, jobs_processed: int, status: string, started_at: int}> */
    private array $workers = [];

    /** @var array<string, int> queue => size */
    private array $queueSizes = [];

    private int $maxRecentJobs;

    public function __construct(int $maxRecentJobs = 1000)
    {
        $this->maxRecentJobs = $maxRecentJobs;
    }

    public function recordJobDispatched(string $jobId, string $className, string $queue, \DateTimeImmutable $timestamp): void
    {
        $this->throughputTimeline[] = [
            'queue' => $queue,
            'type' => 'dispatched',
            'timestamp' => $timestamp->getTimestamp(),
        ];

        $this->addRecentJob($queue, [
            'id' => $jobId,
            'class' => $className,
            'queue' => $queue,
            'status' => 'pending',
            'runtime_ms' => null,
            'attempts' => 0,
            'created_at' => $timestamp->format('c'),
            'completed_at' => null,
        ]);
    }

    public function recordJobProcessed(
        string $jobId,
        string $className,
        string $queue,
        float $runtimeMs,
        \DateTimeImmutable $timestamp,
    ): void {
        $this->counters['processed']++;
        $this->counters['total_runtime'] += $runtimeMs;

        $this->throughputTimeline[] = [
            'queue' => $queue,
            'type' => 'processed',
            'timestamp' => $timestamp->getTimestamp(),
        ];

        $this->runtimeTimeline[] = [
            'queue' => $queue,
            'runtime_ms' => $runtimeMs,
            'timestamp' => $timestamp->getTimestamp(),
        ];

        $this->updateRecentJob($jobId, $queue, [
            'status' => 'completed',
            'runtime_ms' => $runtimeMs,
            'completed_at' => $timestamp->format('c'),
        ]);
    }

    public function recordJobFailed(
        string $jobId,
        string $className,
        string $queue,
        string $exceptionClass,
        string $exceptionMessage,
        int $attemptCount,
        \DateTimeImmutable $timestamp,
    ): void {
        $this->counters['failed']++;

        $this->throughputTimeline[] = [
            'queue' => $queue,
            'type' => 'failed',
            'timestamp' => $timestamp->getTimestamp(),
        ];

        $this->updateRecentJob($jobId, $queue, [
            'status' => 'failed',
            'exception_class' => $exceptionClass,
            'exception_message' => $exceptionMessage,
            'attempts' => $attemptCount,
            'failed_at' => $timestamp->format('c'),
        ]);
    }

    public function recordWaitTime(float $waitTimeMs): void
    {
        $this->counters['total_wait'] += $waitTimeMs;
    }

    public function recordQueueSize(string $queue, int $size): void
    {
        $this->queueSizes[$queue] = $size;
    }

    /**
     * Record or update a worker heartbeat.
     */
    public function recordWorkerHeartbeat(
        string $workerId,
        string $queue,
        int $pid,
        float $memoryMb,
        int $jobsProcessed,
        int $timestamp,
    ): void {
        $existing = $this->workers[$workerId] ?? null;

        $this->workers[$workerId] = [
            'queue' => $queue,
            'pid' => $pid,
            'memory_mb' => $memoryMb,
            'last_heartbeat' => $timestamp,
            'jobs_processed' => $jobsProcessed,
            'status' => 'active',
            'started_at' => $existing['started_at'] ?? $timestamp,
        ];
    }

    public function registerWorker(string $workerId, string $queue, int $pid, int $timestamp): void
    {
        $this->workers[$workerId] = [
            'queue' => $queue,
            'pid' => $pid,
            'memory_mb' => 0.0,
            'last_heartbeat' => $timestamp,
            'jobs_processed' => 0,
            'status' => 'active',
            'started_at' => $timestamp,
        ];
    }

    public function unregisterWorker(string $workerId): void
    {
        unset($this->workers[$workerId]);
    }

    /**
     * Mark workers as inactive if their last heartbeat exceeds the threshold.
     */
    public function detectStaleWorkers(int $currentTimestamp, int $staleThresholdSeconds = 30): int
    {
        $staleCount = 0;

        foreach ($this->workers as $workerId => &$worker) {
            if ($worker['status'] === 'active'
                && ($currentTimestamp - $worker['last_heartbeat']) > $staleThresholdSeconds
            ) {
                $worker['status'] = 'inactive';
                $staleCount++;
            }
        }
        unset($worker);

        return $staleCount;
    }

    /**
     * Remove workers that have been inactive for longer than timeout.
     */
    public function cleanupStaleWorkers(int $currentTimestamp, int $timeoutSeconds = 300): int
    {
        $removedCount = 0;

        foreach ($this->workers as $workerId => $worker) {
            if ($worker['status'] === 'inactive'
                && ($currentTimestamp - $worker['last_heartbeat']) > $timeoutSeconds
            ) {
                unset($this->workers[$workerId]);
                $removedCount++;
            }
        }

        return $removedCount;
    }

    /**
     * Prune metrics data older than the given timestamp.
     */
    public function pruneOlderThan(int $beforeTimestamp): void
    {
        $this->throughputTimeline = array_values(array_filter(
            $this->throughputTimeline,
            fn (array $entry): bool => $entry['timestamp'] >= $beforeTimestamp,
        ));

        $this->runtimeTimeline = array_values(array_filter(
            $this->runtimeTimeline,
            fn (array $entry): bool => $entry['timestamp'] >= $beforeTimestamp,
        ));
    }

    public function getSnapshot(?string $period = '1h'): JobMetrics
    {
        $windowSeconds = $this->periodToSeconds($period);
        $cutoff = time() - $windowSeconds;

        $processedInWindow = 0;
        $failedInWindow = 0;

        foreach ($this->throughputTimeline as $entry) {
            if ($entry['timestamp'] >= $cutoff) {
                if ($entry['type'] === 'processed') {
                    $processedInWindow++;
                } elseif ($entry['type'] === 'failed') {
                    $failedInWindow++;
                }
            }
        }

        $windowMinutes = max(1, $windowSeconds / 60);
        $throughput = $processedInWindow / $windowMinutes;

        $avgRuntime = $this->counters['processed'] > 0
            ? $this->counters['total_runtime'] / $this->counters['processed']
            : 0.0;

        $totalJobs = $this->counters['processed'] + $this->counters['failed'];
        $avgWait = $totalJobs > 0
            ? $this->counters['total_wait'] / $totalJobs
            : 0.0;

        $activeWorkers = count(array_filter(
            $this->workers,
            fn (array $w): bool => $w['status'] === 'active',
        ));

        return new JobMetrics(
            totalProcessed: $this->counters['processed'],
            totalFailed: $this->counters['failed'],
            processedLastHour: $processedInWindow,
            failedLastHour: $failedInWindow,
            throughputPerMinute: round($throughput, 2),
            averageRuntimeMs: round($avgRuntime, 2),
            averageWaitMs: round($avgWait, 2),
            activeWorkers: $activeWorkers,
            queueSizes: $this->queueSizes,
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getRecentJobs(?string $queue = null, int $limit = 25, int $offset = 0, ?string $search = null): array
    {
        $allJobs = [];

        if ($queue !== null) {
            $allJobs = $this->recentJobs[$queue] ?? [];
        } else {
            foreach ($this->recentJobs as $queueJobs) {
                $allJobs = array_merge($allJobs, $queueJobs);
            }
        }

        // Sort by created_at descending (most recent first)
        usort($allJobs, fn (array $a, array $b): int => strcmp((string) $b['created_at'], (string) $a['created_at']));

        if ($search !== null) {
            $search = strtolower($search);
            $allJobs = array_values(array_filter(
                $allJobs,
                fn (array $job): bool => str_contains(strtolower((string) $job['class']), $search),
            ));
        }

        return array_slice($allJobs, $offset, $limit);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getFailedJobs(?string $queue = null, int $limit = 25, int $offset = 0, ?string $search = null): array
    {
        $allJobs = [];

        foreach ($this->recentJobs as $queueName => $queueJobs) {
            if ($queue !== null && $queueName !== $queue) {
                continue;
            }
            foreach ($queueJobs as $job) {
                if (($job['status'] ?? '') === 'failed') {
                    $allJobs[] = $job;
                }
            }
        }

        usort($allJobs, fn (array $a, array $b): int => strcmp((string) ($b['failed_at'] ?? ''), (string) ($a['failed_at'] ?? '')));

        if ($search !== null) {
            $search = strtolower($search);
            $allJobs = array_values(array_filter(
                $allJobs,
                fn (array $job): bool => str_contains(strtolower((string) $job['class']), $search)
                    || str_contains(strtolower((string) ($job['exception_class'] ?? '')), $search)
                    || str_contains(strtolower((string) ($job['exception_message'] ?? '')), $search),
            ));
        }

        return array_slice($allJobs, $offset, $limit);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findJob(string $jobId): ?array
    {
        foreach ($this->recentJobs as $queueJobs) {
            foreach ($queueJobs as $job) {
                if ($job['id'] === $jobId) {
                    return $job;
                }
            }
        }

        return null;
    }

    /**
     * @return array<string, array{queue: string, pid: int, memory_mb: float, last_heartbeat: int, jobs_processed: int, status: string, started_at: int}>
     */
    public function getWorkers(): array
    {
        // Sort: active first, then by last heartbeat descending
        $workers = $this->workers;

        uasort($workers, function (array $a, array $b): int {
            $statusOrder = ['active' => 0, 'inactive' => 1];
            $statusCompare = ($statusOrder[$a['status']] ?? 2) <=> ($statusOrder[$b['status']] ?? 2);
            if ($statusCompare !== 0) {
                return $statusCompare;
            }

            return $b['last_heartbeat'] <=> $a['last_heartbeat'];
        });

        return $workers;
    }

    /**
     * @return array<string, int>
     */
    public function getQueueSizes(): array
    {
        return $this->queueSizes;
    }

    /**
     * @return array{throughput: array<int, array{timestamp: int, count: int}>, runtime: array<int, array{timestamp: int, avg_runtime_ms: float}>}
     */
    public function getTimeSeriesMetrics(string $period = '1h', ?string $queue = null): array
    {
        $windowSeconds = $this->periodToSeconds($period);
        $cutoff = time() - $windowSeconds;

        // Group throughput by minute bucket
        $throughputBuckets = [];
        foreach ($this->throughputTimeline as $entry) {
            if ($entry['timestamp'] < $cutoff) {
                continue;
            }
            if ($queue !== null && $entry['queue'] !== $queue) {
                continue;
            }
            if ($entry['type'] !== 'processed') {
                continue;
            }
            $bucket = (int) floor($entry['timestamp'] / 60) * 60;
            $throughputBuckets[$bucket] = ($throughputBuckets[$bucket] ?? 0) + 1;
        }

        $throughput = [];
        foreach ($throughputBuckets as $ts => $count) {
            $throughput[] = ['timestamp' => $ts, 'count' => $count];
        }
        usort($throughput, fn (array $a, array $b): int => $a['timestamp'] <=> $b['timestamp']);

        // Group runtime by minute bucket
        $runtimeBuckets = [];
        foreach ($this->runtimeTimeline as $entry) {
            if ($entry['timestamp'] < $cutoff) {
                continue;
            }
            if ($queue !== null && $entry['queue'] !== $queue) {
                continue;
            }
            $bucket = (int) floor($entry['timestamp'] / 60) * 60;
            $runtimeBuckets[$bucket][] = $entry['runtime_ms'];
        }

        $runtime = [];
        foreach ($runtimeBuckets as $ts => $runtimes) {
            $runtime[] = [
                'timestamp' => $ts,
                'avg_runtime_ms' => round(array_sum($runtimes) / count($runtimes), 2),
            ];
        }
        usort($runtime, fn (array $a, array $b): int => $a['timestamp'] <=> $b['timestamp']);

        return [
            'throughput' => $throughput,
            'runtime' => $runtime,
        ];
    }

    /**
     * Remove a failed job from the recent jobs list (for delete action).
     */
    public function removeJob(string $jobId): bool
    {
        foreach ($this->recentJobs as $queue => &$jobs) {
            foreach ($jobs as $index => $job) {
                if ($job['id'] === $jobId) {
                    array_splice($jobs, $index, 1);

                    return true;
                }
            }
        }
        unset($jobs);

        return false;
    }

    /**
     * Mark a failed job as retried (update its status).
     */
    public function markJobRetried(string $jobId): bool
    {
        foreach ($this->recentJobs as &$jobs) {
            foreach ($jobs as &$job) {
                if ($job['id'] === $jobId && ($job['status'] ?? '') === 'failed') {
                    $job['status'] = 'retried';

                    return true;
                }
            }
            unset($job);
        }
        unset($jobs);

        return false;
    }

    /**
     * @return array<int, array<string, mixed>> All failed jobs
     */
    public function getAllFailedJobs(): array
    {
        $failedJobs = [];

        foreach ($this->recentJobs as $queueJobs) {
            foreach ($queueJobs as $job) {
                if (($job['status'] ?? '') === 'failed') {
                    $failedJobs[] = $job;
                }
            }
        }

        return $failedJobs;
    }

    /**
     * @param array<string, mixed> $jobData
     */
    private function addRecentJob(string $queue, array $jobData): void
    {
        if (!isset($this->recentJobs[$queue])) {
            $this->recentJobs[$queue] = [];
        }

        array_unshift($this->recentJobs[$queue], $jobData);

        // Cap at max recent jobs per queue
        if (count($this->recentJobs[$queue]) > $this->maxRecentJobs) {
            $this->recentJobs[$queue] = array_slice($this->recentJobs[$queue], 0, $this->maxRecentJobs);
        }
    }

    /**
     * @param array<string, mixed> $updates
     */
    private function updateRecentJob(string $jobId, string $queue, array $updates): void
    {
        if (!isset($this->recentJobs[$queue])) {
            return;
        }

        foreach ($this->recentJobs[$queue] as &$job) {
            if ($job['id'] === $jobId) {
                $job = array_merge($job, $updates);

                return;
            }
        }
        unset($job);

        // Job not found in recent (may have been dispatched before Loom started).
        // Add a partial record so it still appears.
        $this->addRecentJob($queue, array_merge([
            'id' => $jobId,
            'class' => 'Unknown',
            'queue' => $queue,
            'created_at' => null,
        ], $updates));
    }

    private function periodToSeconds(?string $period): int
    {
        return match ($period) {
            '5m' => 300,
            '1h' => 3600,
            '6h' => 21600,
            '24h' => 86400,
            '7d' => 604800,
            default => 3600,
        };
    }
}
