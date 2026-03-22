<?php

declare(strict_types=1);

namespace Lattice\Loom\Tests\Api;

use Lattice\Http\Request;
use Lattice\Loom\Api\DashboardStatsAction;
use Lattice\Loom\Metrics\MetricsStore;
use PHPUnit\Framework\TestCase;

final class DashboardStatsActionTest extends TestCase
{
    private MetricsStore $store;
    private DashboardStatsAction $action;

    protected function setUp(): void
    {
        $this->store = new MetricsStore();
        $this->action = new DashboardStatsAction($this->store);
    }

    public function test_returns_empty_stats_for_fresh_store(): void
    {
        $request = new Request(method: 'GET', uri: '/api/loom/stats');
        $response = ($this->action)($request);

        $this->assertSame(200, $response->getStatusCode());

        $body = $response->getBody();
        $this->assertSame(0, $body['total_processed']);
        $this->assertSame(0, $body['total_failed']);
        $this->assertSame(0.0, $body['throughput_per_minute']);
        $this->assertSame(0.0, $body['avg_runtime_ms']);
        $this->assertSame(0.0, $body['avg_wait_ms']);
        $this->assertSame(0, $body['active_workers']);
        $this->assertSame([], $body['queue_sizes']);
    }

    public function test_returns_populated_stats(): void
    {
        $now = new \DateTimeImmutable();
        $this->store->recordJobDispatched('job-1', 'TestJob', 'default', $now);
        $this->store->recordJobProcessed('job-1', 'TestJob', 'default', 250.0, $now);
        $this->store->recordJobDispatched('job-2', 'TestJob', 'default', $now);
        $this->store->recordJobFailed('job-2', 'TestJob', 'default', 'Exception', 'fail', 1, $now);
        $this->store->recordQueueSize('default', 5);
        $this->store->registerWorker('worker-1', 'default', 123, time());

        $request = new Request(method: 'GET', uri: '/api/loom/stats');
        $response = ($this->action)($request);

        $body = $response->getBody();
        $this->assertSame(1, $body['total_processed']);
        $this->assertSame(1, $body['total_failed']);
        $this->assertSame(250.0, $body['avg_runtime_ms']);
        $this->assertSame(1, $body['active_workers']);
        $this->assertSame(['default' => 5], $body['queue_sizes']);
    }

    public function test_accepts_period_parameter(): void
    {
        $request = new Request(
            method: 'GET',
            uri: '/api/loom/stats',
            query: ['period' => '24h'],
        );

        $response = ($this->action)($request);
        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_invalid_period_defaults_to_1h(): void
    {
        $now = new \DateTimeImmutable();
        $this->store->recordJobDispatched('job-1', 'TestJob', 'default', $now);
        $this->store->recordJobProcessed('job-1', 'TestJob', 'default', 100.0, $now);

        $request = new Request(
            method: 'GET',
            uri: '/api/loom/stats',
            query: ['period' => 'invalid'],
        );

        $response = ($this->action)($request);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(1, $response->getBody()['total_processed']);
    }

    public function test_response_has_json_content_type(): void
    {
        $request = new Request(method: 'GET', uri: '/api/loom/stats');
        $response = ($this->action)($request);

        $this->assertSame('application/json', $response->getHeaders()['Content-Type'] ?? null);
    }
}
