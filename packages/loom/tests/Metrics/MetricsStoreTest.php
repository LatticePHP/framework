<?php

declare(strict_types=1);

namespace Lattice\Loom\Tests\Metrics;

use Lattice\Loom\Metrics\MetricsStore;
use PHPUnit\Framework\TestCase;

final class MetricsStoreTest extends TestCase
{
    private MetricsStore $store;

    protected function setUp(): void
    {
        $this->store = new MetricsStore(maxRecentJobs: 100);
    }

    public function test_records_and_retrieves_dispatched_job(): void
    {
        $now = new \DateTimeImmutable();
        $this->store->recordJobDispatched('job-1', 'App\\Jobs\\SendEmail', 'default', $now);

        $recent = $this->store->getRecentJobs('default');
        $this->assertCount(1, $recent);
        $this->assertSame('job-1', $recent[0]['id']);
        $this->assertSame('App\\Jobs\\SendEmail', $recent[0]['class']);
        $this->assertSame('pending', $recent[0]['status']);
    }

    public function test_records_processed_job_updates_counters(): void
    {
        $now = new \DateTimeImmutable();
        $this->store->recordJobDispatched('job-1', 'App\\Jobs\\TestJob', 'default', $now);
        $this->store->recordJobProcessed('job-1', 'App\\Jobs\\TestJob', 'default', 250.0, $now);

        $snapshot = $this->store->getSnapshot('1h');
        $this->assertSame(1, $snapshot->totalProcessed);
        $this->assertSame(250.0, $snapshot->averageRuntimeMs);
    }

    public function test_records_failed_job_updates_counters(): void
    {
        $now = new \DateTimeImmutable();
        $this->store->recordJobDispatched('job-1', 'App\\Jobs\\TestJob', 'default', $now);
        $this->store->recordJobFailed(
            'job-1',
            'App\\Jobs\\TestJob',
            'default',
            'RuntimeException',
            'Something went wrong',
            1,
            $now,
        );

        $snapshot = $this->store->getSnapshot('1h');
        $this->assertSame(1, $snapshot->totalFailed);

        $failed = $this->store->getFailedJobs();
        $this->assertCount(1, $failed);
        $this->assertSame('RuntimeException', $failed[0]['exception_class']);
    }

    public function test_records_wait_time(): void
    {
        $now = new \DateTimeImmutable();
        $this->store->recordJobDispatched('job-1', 'TestJob', 'default', $now);
        $this->store->recordJobProcessed('job-1', 'TestJob', 'default', 100.0, $now);
        $this->store->recordWaitTime(500.0);

        $snapshot = $this->store->getSnapshot('1h');
        $this->assertSame(500.0, $snapshot->averageWaitMs);
    }

    public function test_records_queue_size(): void
    {
        $this->store->recordQueueSize('default', 42);
        $this->store->recordQueueSize('payments', 7);

        $sizes = $this->store->getQueueSizes();
        $this->assertSame(42, $sizes['default']);
        $this->assertSame(7, $sizes['payments']);

        $snapshot = $this->store->getSnapshot('1h');
        $this->assertSame(['default' => 42, 'payments' => 7], $snapshot->queueSizes);
    }

    public function test_recent_jobs_filters_by_queue(): void
    {
        $now = new \DateTimeImmutable();
        $this->store->recordJobDispatched('job-1', 'TestJob', 'default', $now);
        $this->store->recordJobDispatched('job-2', 'TestJob', 'payments', $now);
        $this->store->recordJobDispatched('job-3', 'TestJob', 'default', $now);

        $defaultJobs = $this->store->getRecentJobs('default');
        $this->assertCount(2, $defaultJobs);

        $paymentJobs = $this->store->getRecentJobs('payments');
        $this->assertCount(1, $paymentJobs);
    }

    public function test_recent_jobs_search_by_class_name(): void
    {
        $now = new \DateTimeImmutable();
        $this->store->recordJobDispatched('job-1', 'App\\Jobs\\SendEmail', 'default', $now);
        $this->store->recordJobDispatched('job-2', 'App\\Jobs\\ProcessPayment', 'default', $now);
        $this->store->recordJobDispatched('job-3', 'App\\Jobs\\SendNotification', 'default', $now);

        $found = $this->store->getRecentJobs(search: 'Send');
        $this->assertCount(2, $found);
    }

    public function test_recent_jobs_pagination(): void
    {
        $now = new \DateTimeImmutable();
        for ($i = 1; $i <= 10; $i++) {
            $this->store->recordJobDispatched("job-{$i}", "TestJob{$i}", 'default', $now);
        }

        $page1 = $this->store->getRecentJobs(limit: 3, offset: 0);
        $this->assertCount(3, $page1);

        $page2 = $this->store->getRecentJobs(limit: 3, offset: 3);
        $this->assertCount(3, $page2);

        $this->assertNotSame($page1[0]['id'], $page2[0]['id']);
    }

    public function test_failed_jobs_filters_by_queue(): void
    {
        $now = new \DateTimeImmutable();
        $this->store->recordJobDispatched('job-1', 'TestJob', 'default', $now);
        $this->store->recordJobFailed('job-1', 'TestJob', 'default', 'Exception', 'fail', 1, $now);

        $this->store->recordJobDispatched('job-2', 'TestJob', 'payments', $now);
        $this->store->recordJobFailed('job-2', 'TestJob', 'payments', 'Exception', 'fail', 1, $now);

        $defaultFailed = $this->store->getFailedJobs('default');
        $this->assertCount(1, $defaultFailed);

        $paymentFailed = $this->store->getFailedJobs('payments');
        $this->assertCount(1, $paymentFailed);
    }

    public function test_failed_jobs_search_by_exception(): void
    {
        $now = new \DateTimeImmutable();
        $this->store->recordJobDispatched('job-1', 'TestJob', 'default', $now);
        $this->store->recordJobFailed('job-1', 'TestJob', 'default', 'TimeoutException', 'Timed out', 1, $now);

        $this->store->recordJobDispatched('job-2', 'TestJob', 'default', $now);
        $this->store->recordJobFailed('job-2', 'TestJob', 'default', 'RuntimeException', 'Runtime error', 1, $now);

        $found = $this->store->getFailedJobs(search: 'Timeout');
        $this->assertCount(1, $found);
        $this->assertSame('job-1', $found[0]['id']);
    }

    public function test_find_job_by_id(): void
    {
        $now = new \DateTimeImmutable();
        $this->store->recordJobDispatched('job-1', 'TestJob', 'default', $now);

        $job = $this->store->findJob('job-1');
        $this->assertNotNull($job);
        $this->assertSame('job-1', $job['id']);

        $notFound = $this->store->findJob('nonexistent');
        $this->assertNull($notFound);
    }

    public function test_worker_heartbeat_recording(): void
    {
        $this->store->registerWorker('worker-1', 'default', 12345, 1000);
        $this->store->recordWorkerHeartbeat('worker-1', 'default', 12345, 64.5, 10, 1015);

        $workers = $this->store->getWorkers();
        $this->assertCount(1, $workers);
        $this->assertSame('active', $workers['worker-1']['status']);
        $this->assertSame(64.5, $workers['worker-1']['memory_mb']);
        $this->assertSame(10, $workers['worker-1']['jobs_processed']);
    }

    public function test_detects_stale_workers(): void
    {
        $this->store->registerWorker('worker-1', 'default', 111, 1000);
        $this->store->registerWorker('worker-2', 'default', 222, 1000);

        // Update worker-1 recently, leave worker-2 stale
        $this->store->recordWorkerHeartbeat('worker-1', 'default', 111, 64.0, 5, 1050);

        $staleCount = $this->store->detectStaleWorkers(1060, staleThresholdSeconds: 30);
        $this->assertSame(1, $staleCount);

        $workers = $this->store->getWorkers();
        $this->assertSame('active', $workers['worker-1']['status']);
        $this->assertSame('inactive', $workers['worker-2']['status']);
    }

    public function test_cleans_up_stale_workers(): void
    {
        $this->store->registerWorker('worker-1', 'default', 111, 1000);

        $this->store->detectStaleWorkers(1060, staleThresholdSeconds: 30);
        $this->assertCount(1, $this->store->getWorkers());

        $removed = $this->store->cleanupStaleWorkers(1400, timeoutSeconds: 300);
        $this->assertSame(1, $removed);
        $this->assertCount(0, $this->store->getWorkers());
    }

    public function test_unregister_worker(): void
    {
        $this->store->registerWorker('worker-1', 'default', 111, 1000);
        $this->assertCount(1, $this->store->getWorkers());

        $this->store->unregisterWorker('worker-1');
        $this->assertCount(0, $this->store->getWorkers());
    }

    public function test_workers_sorted_active_first(): void
    {
        $this->store->registerWorker('worker-1', 'default', 111, 1000);
        $this->store->registerWorker('worker-2', 'default', 222, 1000);
        $this->store->recordWorkerHeartbeat('worker-2', 'default', 222, 64.0, 5, 1050);

        $this->store->detectStaleWorkers(1060, staleThresholdSeconds: 30);

        $workers = $this->store->getWorkers();
        $keys = array_keys($workers);
        $this->assertSame('worker-2', $keys[0]); // active
        $this->assertSame('worker-1', $keys[1]); // inactive
    }

    public function test_prune_old_metrics(): void
    {
        $oldTime = new \DateTimeImmutable('@' . (time() - 7200)); // 2 hours ago
        $newTime = new \DateTimeImmutable();

        $this->store->recordJobDispatched('job-old', 'TestJob', 'default', $oldTime);
        $this->store->recordJobProcessed('job-old', 'TestJob', 'default', 100.0, $oldTime);

        $this->store->recordJobDispatched('job-new', 'TestJob', 'default', $newTime);
        $this->store->recordJobProcessed('job-new', 'TestJob', 'default', 200.0, $newTime);

        $cutoff = time() - 3600; // 1 hour ago
        $this->store->pruneOlderThan($cutoff);

        // Counters should still reflect both (counters are aggregate, not pruned)
        $snapshot = $this->store->getSnapshot('1h');
        $this->assertSame(2, $snapshot->totalProcessed);

        // But time-series data for the old job should be gone
        $metrics = $this->store->getTimeSeriesMetrics('1h');
        foreach ($metrics['throughput'] as $entry) {
            $this->assertGreaterThanOrEqual($cutoff, $entry['timestamp']);
        }
    }

    public function test_caps_recent_jobs_at_max(): void
    {
        $store = new MetricsStore(maxRecentJobs: 5);
        $now = new \DateTimeImmutable();

        for ($i = 1; $i <= 10; $i++) {
            $store->recordJobDispatched("job-{$i}", 'TestJob', 'default', $now);
        }

        $recent = $store->getRecentJobs('default');
        $this->assertCount(5, $recent);
    }

    public function test_remove_job(): void
    {
        $now = new \DateTimeImmutable();
        $this->store->recordJobDispatched('job-1', 'TestJob', 'default', $now);

        $this->assertTrue($this->store->removeJob('job-1'));
        $this->assertNull($this->store->findJob('job-1'));
        $this->assertFalse($this->store->removeJob('nonexistent'));
    }

    public function test_mark_job_retried(): void
    {
        $now = new \DateTimeImmutable();
        $this->store->recordJobDispatched('job-1', 'TestJob', 'default', $now);
        $this->store->recordJobFailed('job-1', 'TestJob', 'default', 'Exception', 'fail', 1, $now);

        $this->assertTrue($this->store->markJobRetried('job-1'));

        $job = $this->store->findJob('job-1');
        $this->assertSame('retried', $job['status']);

        // Should not appear in failed jobs anymore
        $failed = $this->store->getFailedJobs();
        $this->assertCount(0, $failed);
    }

    public function test_get_all_failed_jobs(): void
    {
        $now = new \DateTimeImmutable();

        $this->store->recordJobDispatched('job-1', 'TestJob', 'default', $now);
        $this->store->recordJobFailed('job-1', 'TestJob', 'default', 'Exception', 'fail1', 1, $now);

        $this->store->recordJobDispatched('job-2', 'TestJob', 'payments', $now);
        $this->store->recordJobFailed('job-2', 'TestJob', 'payments', 'Exception', 'fail2', 1, $now);

        $this->store->recordJobDispatched('job-3', 'TestJob', 'default', $now);
        $this->store->recordJobProcessed('job-3', 'TestJob', 'default', 100.0, $now);

        $allFailed = $this->store->getAllFailedJobs();
        $this->assertCount(2, $allFailed);
    }

    public function test_snapshot_with_different_periods(): void
    {
        $now = new \DateTimeImmutable();
        $this->store->recordJobDispatched('job-1', 'TestJob', 'default', $now);
        $this->store->recordJobProcessed('job-1', 'TestJob', 'default', 100.0, $now);

        $snapshot5m = $this->store->getSnapshot('5m');
        $this->assertSame(1, $snapshot5m->processedLastHour);

        $snapshot1h = $this->store->getSnapshot('1h');
        $this->assertSame(1, $snapshot1h->processedLastHour);
    }

    public function test_time_series_metrics(): void
    {
        $now = new \DateTimeImmutable();
        $this->store->recordJobDispatched('job-1', 'TestJob', 'default', $now);
        $this->store->recordJobProcessed('job-1', 'TestJob', 'default', 100.0, $now);

        $this->store->recordJobDispatched('job-2', 'TestJob', 'default', $now);
        $this->store->recordJobProcessed('job-2', 'TestJob', 'default', 200.0, $now);

        $metrics = $this->store->getTimeSeriesMetrics('1h');
        $this->assertArrayHasKey('throughput', $metrics);
        $this->assertArrayHasKey('runtime', $metrics);
        $this->assertNotEmpty($metrics['throughput']);
        $this->assertNotEmpty($metrics['runtime']);

        // Both jobs should be in the same minute bucket
        $this->assertSame(2, $metrics['throughput'][0]['count']);
        $this->assertSame(150.0, $metrics['runtime'][0]['avg_runtime_ms']);
    }

    public function test_time_series_filters_by_queue(): void
    {
        $now = new \DateTimeImmutable();
        $this->store->recordJobDispatched('job-1', 'TestJob', 'default', $now);
        $this->store->recordJobProcessed('job-1', 'TestJob', 'default', 100.0, $now);

        $this->store->recordJobDispatched('job-2', 'TestJob', 'payments', $now);
        $this->store->recordJobProcessed('job-2', 'TestJob', 'payments', 200.0, $now);

        $metrics = $this->store->getTimeSeriesMetrics('1h', 'default');
        $this->assertNotEmpty($metrics['throughput']);
        $this->assertSame(1, $metrics['throughput'][0]['count']);
    }
}
