<?php

declare(strict_types=1);

namespace Lattice\Loom\Tests\Api;

use Lattice\Http\Request;
use Lattice\Loom\Api\RetryJobAction;
use Lattice\Loom\Metrics\MetricsStore;
use Lattice\Loom\Tests\Fixtures\DummyJob;
use Lattice\Queue\Dispatcher;
use Lattice\Queue\Driver\InMemoryDriver;
use Lattice\Queue\Failed\InMemoryFailedJobStore;
use Lattice\Queue\SerializedJob;
use PHPUnit\Framework\TestCase;

final class RetryJobActionTest extends TestCase
{
    private MetricsStore $store;
    private InMemoryFailedJobStore $failedJobStore;
    private InMemoryDriver $queueDriver;
    private Dispatcher $dispatcher;
    private RetryJobAction $action;

    protected function setUp(): void
    {
        $this->store = new MetricsStore();
        $this->failedJobStore = new InMemoryFailedJobStore();
        $this->queueDriver = new InMemoryDriver();
        $this->dispatcher = new Dispatcher($this->queueDriver);
        $this->action = new RetryJobAction(
            $this->store,
            $this->failedJobStore,
            $this->dispatcher,
        );
    }

    public function test_retries_failed_job(): void
    {
        $job = new DummyJob();
        $serialized = SerializedJob::fromJob($job);
        $this->failedJobStore->store($serialized, new \RuntimeException('Test failure'));

        $now = new \DateTimeImmutable();
        $this->store->recordJobDispatched($serialized->id, DummyJob::class, 'default', $now);
        $this->store->recordJobFailed($serialized->id, DummyJob::class, 'default', 'RuntimeException', 'Test failure', 1, $now);

        $request = new Request(
            method: 'POST',
            uri: "/api/loom/jobs/{$serialized->id}/retry",
            pathParams: ['id' => $serialized->id],
        );

        $response = ($this->action)($request);

        $this->assertSame(200, $response->getStatusCode());
        $body = $response->getBody();
        $this->assertSame('retried', $body['status']);
        $this->assertSame($serialized->id, $body['job_id']);

        // Job should be re-queued
        $dispatched = $this->queueDriver->getDispatched();
        $this->assertCount(1, $dispatched);
    }

    public function test_returns_404_for_nonexistent_job(): void
    {
        $request = new Request(
            method: 'POST',
            uri: '/api/loom/jobs/nonexistent/retry',
            pathParams: ['id' => 'nonexistent'],
        );

        $response = ($this->action)($request);

        $this->assertSame(404, $response->getStatusCode());
        $body = $response->getBody();
        $this->assertSame('Failed job not found', $body['error']);
    }

    public function test_returns_400_when_no_id(): void
    {
        $request = new Request(
            method: 'POST',
            uri: '/api/loom/jobs//retry',
        );

        $response = ($this->action)($request);

        $this->assertSame(400, $response->getStatusCode());
    }

    public function test_marks_job_as_retried_in_metrics(): void
    {
        $job = new DummyJob();
        $serialized = SerializedJob::fromJob($job);
        $this->failedJobStore->store($serialized, new \RuntimeException('fail'));

        $now = new \DateTimeImmutable();
        $this->store->recordJobDispatched($serialized->id, DummyJob::class, 'default', $now);
        $this->store->recordJobFailed($serialized->id, DummyJob::class, 'default', 'RuntimeException', 'fail', 1, $now);

        $request = new Request(
            method: 'POST',
            uri: "/api/loom/jobs/{$serialized->id}/retry",
            pathParams: ['id' => $serialized->id],
        );

        ($this->action)($request);

        // Job should be marked as retried in store
        $jobData = $this->store->findJob($serialized->id);
        $this->assertSame('retried', $jobData['status']);

        // Should no longer appear in failed jobs
        $failed = $this->store->getFailedJobs();
        $this->assertCount(0, $failed);
    }
}
