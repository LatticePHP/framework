<?php

declare(strict_types=1);

namespace Lattice\Chronos\Tests\Api;

use Lattice\Chronos\Api\WorkflowStatsAction;
use Lattice\Chronos\InMemoryChronosEventStore;
use Lattice\Contracts\Workflow\WorkflowStatus;
use Lattice\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class WorkflowStatsActionTest extends TestCase
{
    private InMemoryChronosEventStore $eventStore;
    private WorkflowStatsAction $action;

    protected function setUp(): void
    {
        $this->eventStore = new InMemoryChronosEventStore();
        $this->action = new WorkflowStatsAction($this->eventStore, cacheTtlSeconds: 0);
    }

    #[Test]
    public function it_returns_zero_stats_when_empty(): void
    {
        $request = new Request('GET', '/api/chronos/stats');
        $response = ($this->action)($request);

        $this->assertSame(200, $response->getStatusCode());

        $body = $response->getBody();
        $data = $body['data'];

        $this->assertSame(0, $data['running']);
        $this->assertSame(0, $data['completed']);
        $this->assertSame(0, $data['failed']);
        $this->assertSame(0, $data['cancelled']);
        $this->assertSame(0.0, $data['avg_duration_ms']);
    }

    #[Test]
    public function it_returns_correct_running_count(): void
    {
        $this->eventStore->createExecution('Workflow', 'wf-1', 'run-1', null);
        $this->eventStore->createExecution('Workflow', 'wf-2', 'run-2', null);
        $this->eventStore->createExecution('Workflow', 'wf-3', 'run-3', null);

        $request = new Request('GET', '/api/chronos/stats');
        $response = ($this->action)($request);

        $body = $response->getBody();
        $this->assertSame(3, $body['data']['running']);
    }

    #[Test]
    public function it_returns_correct_counts_for_all_statuses(): void
    {
        // 2 running
        $this->eventStore->createExecution('Workflow', 'wf-1', 'run-1', null);
        $this->eventStore->createExecution('Workflow', 'wf-2', 'run-2', null);

        // 1 completed
        $exec3 = $this->eventStore->createExecution('Workflow', 'wf-3', 'run-3', null);
        $this->eventStore->updateExecutionStatus($exec3, WorkflowStatus::Completed, 'done');

        // 1 failed
        $exec4 = $this->eventStore->createExecution('Workflow', 'wf-4', 'run-4', null);
        $this->eventStore->updateExecutionStatus($exec4, WorkflowStatus::Failed);

        // 1 cancelled
        $exec5 = $this->eventStore->createExecution('Workflow', 'wf-5', 'run-5', null);
        $this->eventStore->updateExecutionStatus($exec5, WorkflowStatus::Cancelled);

        $request = new Request('GET', '/api/chronos/stats');
        $response = ($this->action)($request);

        $body = $response->getBody();
        $data = $body['data'];

        $this->assertSame(2, $data['running']);
        $this->assertSame(1, $data['completed']);
        $this->assertSame(1, $data['failed']);
        $this->assertSame(1, $data['cancelled']);
    }

    #[Test]
    public function it_returns_stats_response_envelope(): void
    {
        $request = new Request('GET', '/api/chronos/stats');
        $response = ($this->action)($request);

        $body = $response->getBody();
        $this->assertArrayHasKey('data', $body);
        $this->assertArrayHasKey('running', $body['data']);
        $this->assertArrayHasKey('completed', $body['data']);
        $this->assertArrayHasKey('failed', $body['data']);
        $this->assertArrayHasKey('cancelled', $body['data']);
        $this->assertArrayHasKey('avg_duration_ms', $body['data']);
    }

    #[Test]
    public function it_caches_stats_within_ttl(): void
    {
        // Use a long TTL for caching test
        $action = new WorkflowStatsAction($this->eventStore, cacheTtlSeconds: 60);

        $this->eventStore->createExecution('Workflow', 'wf-1', 'run-1', null);

        $request = new Request('GET', '/api/chronos/stats');

        // First call: running = 1
        $response1 = $action($request);
        $this->assertSame(1, $response1->getBody()['data']['running']);

        // Add another execution
        $this->eventStore->createExecution('Workflow', 'wf-2', 'run-2', null);

        // Second call: should still return cached value (running = 1)
        $response2 = $action($request);
        $this->assertSame(1, $response2->getBody()['data']['running']);

        // Clear cache
        $action->clearCache();

        // Third call: should return fresh value (running = 2)
        $response3 = $action($request);
        $this->assertSame(2, $response3->getBody()['data']['running']);
    }

    #[Test]
    public function it_does_not_cache_when_ttl_is_zero(): void
    {
        $this->eventStore->createExecution('Workflow', 'wf-1', 'run-1', null);

        $request = new Request('GET', '/api/chronos/stats');
        $response1 = ($this->action)($request);
        $this->assertSame(1, $response1->getBody()['data']['running']);

        $this->eventStore->createExecution('Workflow', 'wf-2', 'run-2', null);

        $response2 = ($this->action)($request);
        $this->assertSame(2, $response2->getBody()['data']['running']);
    }
}
