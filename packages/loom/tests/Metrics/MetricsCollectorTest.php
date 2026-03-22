<?php

declare(strict_types=1);

namespace Lattice\Loom\Tests\Metrics;

use Lattice\Events\EventDispatcher;
use Lattice\Loom\Events\JobDispatched;
use Lattice\Loom\Events\JobFailed;
use Lattice\Loom\Events\JobProcessed;
use Lattice\Loom\Metrics\MetricsCollector;
use Lattice\Loom\Metrics\MetricsStore;
use PHPUnit\Framework\TestCase;

final class MetricsCollectorTest extends TestCase
{
    private MetricsStore $store;
    private MetricsCollector $collector;
    private EventDispatcher $dispatcher;

    protected function setUp(): void
    {
        $this->store = new MetricsStore();
        $this->collector = new MetricsCollector($this->store);
        $this->dispatcher = new EventDispatcher();
        $this->collector->register($this->dispatcher);
    }

    public function test_records_dispatched_job_via_event(): void
    {
        $event = new JobDispatched(
            jobId: 'job-001',
            className: 'App\\Jobs\\SendEmail',
            queue: 'default',
            tags: ['mail'],
            timestamp: new \DateTimeImmutable(),
        );

        $this->dispatcher->dispatch($event);

        $recent = $this->store->getRecentJobs('default');
        $this->assertCount(1, $recent);
        $this->assertSame('job-001', $recent[0]['id']);
        $this->assertSame('App\\Jobs\\SendEmail', $recent[0]['class']);
        $this->assertSame('pending', $recent[0]['status']);
    }

    public function test_records_processed_job_via_event(): void
    {
        // First dispatch, then process
        $this->dispatcher->dispatch(new JobDispatched(
            jobId: 'job-002',
            className: 'App\\Jobs\\ProcessPayment',
            queue: 'payments',
            tags: [],
            timestamp: new \DateTimeImmutable(),
        ));

        $this->dispatcher->dispatch(new JobProcessed(
            jobId: 'job-002',
            className: 'App\\Jobs\\ProcessPayment',
            queue: 'payments',
            runtimeMs: 150.5,
            timestamp: new \DateTimeImmutable(),
        ));

        $recent = $this->store->getRecentJobs('payments');
        $this->assertCount(1, $recent);
        $this->assertSame('completed', $recent[0]['status']);
        $this->assertSame(150.5, $recent[0]['runtime_ms']);

        $snapshot = $this->store->getSnapshot('1h');
        $this->assertSame(1, $snapshot->totalProcessed);
        $this->assertSame(150.5, $snapshot->averageRuntimeMs);
    }

    public function test_records_failed_job_via_event(): void
    {
        $this->dispatcher->dispatch(new JobDispatched(
            jobId: 'job-003',
            className: 'App\\Jobs\\ImportData',
            queue: 'imports',
            tags: ['import'],
            timestamp: new \DateTimeImmutable(),
        ));

        $this->dispatcher->dispatch(new JobFailed(
            jobId: 'job-003',
            className: 'App\\Jobs\\ImportData',
            queue: 'imports',
            exceptionClass: 'RuntimeException',
            exceptionMessage: 'Connection timed out',
            trace: ['#0 file.php:10'],
            attemptCount: 3,
            timestamp: new \DateTimeImmutable(),
        ));

        $failed = $this->store->getFailedJobs('imports');
        $this->assertCount(1, $failed);
        $this->assertSame('failed', $failed[0]['status']);
        $this->assertSame('RuntimeException', $failed[0]['exception_class']);
        $this->assertSame('Connection timed out', $failed[0]['exception_message']);

        $snapshot = $this->store->getSnapshot('1h');
        $this->assertSame(1, $snapshot->totalFailed);
    }

    public function test_subscriber_returns_correct_event_map(): void
    {
        $events = MetricsCollector::getSubscribedEvents();

        $this->assertArrayHasKey(JobDispatched::class, $events);
        $this->assertArrayHasKey(JobProcessed::class, $events);
        $this->assertArrayHasKey(JobFailed::class, $events);
        $this->assertSame('onJobDispatched', $events[JobDispatched::class]);
        $this->assertSame('onJobProcessed', $events[JobProcessed::class]);
        $this->assertSame('onJobFailed', $events[JobFailed::class]);
    }

    public function test_multiple_events_accumulate_metrics(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->dispatcher->dispatch(new JobDispatched(
                jobId: "job-{$i}",
                className: 'App\\Jobs\\TestJob',
                queue: 'default',
                tags: [],
                timestamp: new \DateTimeImmutable(),
            ));
            $this->dispatcher->dispatch(new JobProcessed(
                jobId: "job-{$i}",
                className: 'App\\Jobs\\TestJob',
                queue: 'default',
                runtimeMs: (float) ($i * 10),
                timestamp: new \DateTimeImmutable(),
            ));
        }

        $snapshot = $this->store->getSnapshot('1h');
        $this->assertSame(5, $snapshot->totalProcessed);
        // Average of 10, 20, 30, 40, 50 = 30.0
        $this->assertSame(30.0, $snapshot->averageRuntimeMs);
    }
}
