<?php

declare(strict_types=1);

namespace Lattice\Loom\Tests\Api;

use Lattice\Http\Request;
use Lattice\Loom\Api\FailedJobsAction;
use Lattice\Loom\Metrics\MetricsStore;
use PHPUnit\Framework\TestCase;

final class FailedJobsActionTest extends TestCase
{
    private MetricsStore $store;
    private FailedJobsAction $action;

    protected function setUp(): void
    {
        $this->store = new MetricsStore();
        $this->action = new FailedJobsAction($this->store);
    }

    public function test_returns_empty_list_when_no_failed_jobs(): void
    {
        $request = new Request(method: 'GET', uri: '/api/loom/jobs/failed');
        $response = ($this->action)($request);

        $this->assertSame(200, $response->getStatusCode());
        $body = $response->getBody();
        $this->assertSame([], $body['data']);
        $this->assertSame(1, $body['meta']['page']);
        $this->assertSame(25, $body['meta']['per_page']);
    }

    public function test_returns_failed_jobs(): void
    {
        $now = new \DateTimeImmutable();
        $this->store->recordJobDispatched('job-1', 'App\\Jobs\\SendEmail', 'default', $now);
        $this->store->recordJobFailed(
            'job-1',
            'App\\Jobs\\SendEmail',
            'default',
            'RuntimeException',
            'SMTP connection failed',
            3,
            $now,
        );

        $request = new Request(method: 'GET', uri: '/api/loom/jobs/failed');
        $response = ($this->action)($request);

        $body = $response->getBody();
        $this->assertCount(1, $body['data']);
        $this->assertSame('job-1', $body['data'][0]['id']);
        $this->assertSame('failed', $body['data'][0]['status']);
        $this->assertSame('RuntimeException', $body['data'][0]['exception_class']);
    }

    public function test_filters_by_queue(): void
    {
        $now = new \DateTimeImmutable();
        $this->store->recordJobDispatched('job-1', 'TestJob', 'default', $now);
        $this->store->recordJobFailed('job-1', 'TestJob', 'default', 'Exception', 'fail', 1, $now);

        $this->store->recordJobDispatched('job-2', 'TestJob', 'payments', $now);
        $this->store->recordJobFailed('job-2', 'TestJob', 'payments', 'Exception', 'fail', 1, $now);

        $request = new Request(
            method: 'GET',
            uri: '/api/loom/jobs/failed',
            query: ['queue' => 'default'],
        );
        $response = ($this->action)($request);

        $body = $response->getBody();
        $this->assertCount(1, $body['data']);
        $this->assertSame('job-1', $body['data'][0]['id']);
    }

    public function test_search_by_exception_message(): void
    {
        $now = new \DateTimeImmutable();
        $this->store->recordJobDispatched('job-1', 'TestJob', 'default', $now);
        $this->store->recordJobFailed('job-1', 'TestJob', 'default', 'TimeoutException', 'Connection timed out', 1, $now);

        $this->store->recordJobDispatched('job-2', 'TestJob', 'default', $now);
        $this->store->recordJobFailed('job-2', 'TestJob', 'default', 'RuntimeException', 'Null pointer', 1, $now);

        $request = new Request(
            method: 'GET',
            uri: '/api/loom/jobs/failed',
            query: ['search' => 'timed out'],
        );
        $response = ($this->action)($request);

        $body = $response->getBody();
        $this->assertCount(1, $body['data']);
        $this->assertSame('job-1', $body['data'][0]['id']);
    }

    public function test_pagination(): void
    {
        $now = new \DateTimeImmutable();
        for ($i = 1; $i <= 5; $i++) {
            $this->store->recordJobDispatched("job-{$i}", 'TestJob', 'default', $now);
            $this->store->recordJobFailed("job-{$i}", 'TestJob', 'default', 'Exception', 'fail', 1, $now);
        }

        $request = new Request(
            method: 'GET',
            uri: '/api/loom/jobs/failed',
            query: ['page' => '1', 'per_page' => '2'],
        );
        $response = ($this->action)($request);

        $body = $response->getBody();
        $this->assertCount(2, $body['data']);
        $this->assertSame(1, $body['meta']['page']);
        $this->assertSame(2, $body['meta']['per_page']);
    }

    public function test_does_not_include_non_failed_jobs(): void
    {
        $now = new \DateTimeImmutable();
        $this->store->recordJobDispatched('job-1', 'TestJob', 'default', $now);
        $this->store->recordJobProcessed('job-1', 'TestJob', 'default', 100.0, $now);

        $request = new Request(method: 'GET', uri: '/api/loom/jobs/failed');
        $response = ($this->action)($request);

        $body = $response->getBody();
        $this->assertSame([], $body['data']);
    }
}
