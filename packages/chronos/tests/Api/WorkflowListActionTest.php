<?php

declare(strict_types=1);

namespace Lattice\Chronos\Tests\Api;

use DateTimeImmutable;
use Lattice\Chronos\Api\WorkflowListAction;
use Lattice\Chronos\InMemoryChronosEventStore;
use Lattice\Contracts\Workflow\WorkflowStatus;
use Lattice\Http\Request;
use Lattice\Workflow\Event\WorkflowEvent;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class WorkflowListActionTest extends TestCase
{
    private InMemoryChronosEventStore $eventStore;
    private WorkflowListAction $action;

    protected function setUp(): void
    {
        $this->eventStore = new InMemoryChronosEventStore();
        $this->action = new WorkflowListAction($this->eventStore);
    }

    #[Test]
    public function it_returns_empty_list_when_no_workflows(): void
    {
        $request = new Request('GET', '/api/chronos/workflows');
        $response = ($this->action)($request);

        $this->assertSame(200, $response->getStatusCode());

        $body = $response->getBody();
        $this->assertSame([], $body['data']);
        $this->assertSame(0, $body['meta']['total']);
        $this->assertFalse($body['meta']['has_more']);
    }

    #[Test]
    public function it_returns_paginated_workflow_list(): void
    {
        $this->seedWorkflows(5);

        $request = new Request('GET', '/api/chronos/workflows', query: ['page' => '1', 'per_page' => '2']);
        $response = ($this->action)($request);

        $body = $response->getBody();
        $this->assertCount(2, $body['data']);
        $this->assertSame(5, $body['meta']['total']);
        $this->assertSame(1, $body['meta']['page']);
        $this->assertSame(2, $body['meta']['per_page']);
        $this->assertTrue($body['meta']['has_more']);
    }

    #[Test]
    public function it_returns_last_page_with_has_more_false(): void
    {
        $this->seedWorkflows(5);

        $request = new Request('GET', '/api/chronos/workflows', query: ['page' => '3', 'per_page' => '2']);
        $response = ($this->action)($request);

        $body = $response->getBody();
        $this->assertCount(1, $body['data']);
        $this->assertFalse($body['meta']['has_more']);
    }

    #[Test]
    public function it_filters_by_status(): void
    {
        $this->seedWorkflows(3);
        // Make the first one failed
        $this->eventStore->updateExecutionStatus('exec_1', WorkflowStatus::Failed);

        $request = new Request('GET', '/api/chronos/workflows', query: ['status' => 'failed']);
        $response = ($this->action)($request);

        $body = $response->getBody();
        $this->assertCount(1, $body['data']);
        $this->assertSame('failed', $body['data'][0]['status']);
    }

    #[Test]
    public function it_filters_by_multiple_statuses(): void
    {
        $this->seedWorkflows(4);
        $this->eventStore->updateExecutionStatus('exec_1', WorkflowStatus::Failed);
        $this->eventStore->updateExecutionStatus('exec_2', WorkflowStatus::Cancelled);
        $this->eventStore->updateExecutionStatus('exec_3', WorkflowStatus::Completed, 'done');

        $request = new Request('GET', '/api/chronos/workflows', query: ['status' => 'failed,cancelled']);
        $response = ($this->action)($request);

        $body = $response->getBody();
        $this->assertCount(2, $body['data']);
    }

    #[Test]
    public function it_filters_by_workflow_type(): void
    {
        $this->eventStore->createExecution('OrderWorkflow', 'wf-1', 'run-1', null);
        $this->eventStore->createExecution('PaymentWorkflow', 'wf-2', 'run-2', null);
        $this->eventStore->createExecution('OrderWorkflow', 'wf-3', 'run-3', null);

        $request = new Request('GET', '/api/chronos/workflows', query: ['type' => 'OrderWorkflow']);
        $response = ($this->action)($request);

        $body = $response->getBody();
        $this->assertCount(2, $body['data']);
        foreach ($body['data'] as $item) {
            $this->assertSame('OrderWorkflow', $item['type']);
        }
    }

    #[Test]
    public function it_filters_by_date_range(): void
    {
        $this->eventStore->createExecutionWithTimestamp(
            'Workflow',
            'wf-old',
            'run-1',
            null,
            new DateTimeImmutable('2026-01-01 10:00:00'),
        );
        $this->eventStore->createExecutionWithTimestamp(
            'Workflow',
            'wf-mid',
            'run-2',
            null,
            new DateTimeImmutable('2026-02-15 10:00:00'),
        );
        $this->eventStore->createExecutionWithTimestamp(
            'Workflow',
            'wf-new',
            'run-3',
            null,
            new DateTimeImmutable('2026-03-20 10:00:00'),
        );

        $request = new Request('GET', '/api/chronos/workflows', query: [
            'from' => '2026-02-01',
            'to' => '2026-02-28',
        ]);
        $response = ($this->action)($request);

        $body = $response->getBody();
        $this->assertCount(1, $body['data']);
        $this->assertSame('wf-mid', $body['data'][0]['workflow_id']);
    }

    #[Test]
    public function it_searches_by_workflow_id(): void
    {
        $this->eventStore->createExecution('Workflow', 'order-123', 'run-1', null);
        $this->eventStore->createExecution('Workflow', 'payment-456', 'run-2', null);
        $this->eventStore->createExecution('Workflow', 'order-789', 'run-3', null);

        $request = new Request('GET', '/api/chronos/workflows', query: ['search' => 'order']);
        $response = ($this->action)($request);

        $body = $response->getBody();
        $this->assertCount(2, $body['data']);
    }

    #[Test]
    public function it_sorts_by_started_at_desc_by_default(): void
    {
        $this->eventStore->createExecutionWithTimestamp(
            'Workflow',
            'wf-old',
            'run-1',
            null,
            new DateTimeImmutable('2026-01-01'),
        );
        $this->eventStore->createExecutionWithTimestamp(
            'Workflow',
            'wf-new',
            'run-2',
            null,
            new DateTimeImmutable('2026-03-01'),
        );

        $request = new Request('GET', '/api/chronos/workflows');
        $response = ($this->action)($request);

        $body = $response->getBody();
        $this->assertCount(2, $body['data']);
        $this->assertSame('wf-new', $body['data'][0]['workflow_id']);
        $this->assertSame('wf-old', $body['data'][1]['workflow_id']);
    }

    #[Test]
    public function it_sorts_ascending_when_requested(): void
    {
        $this->eventStore->createExecutionWithTimestamp(
            'Workflow',
            'wf-old',
            'run-1',
            null,
            new DateTimeImmutable('2026-01-01'),
        );
        $this->eventStore->createExecutionWithTimestamp(
            'Workflow',
            'wf-new',
            'run-2',
            null,
            new DateTimeImmutable('2026-03-01'),
        );

        $request = new Request('GET', '/api/chronos/workflows', query: ['order' => 'asc']);
        $response = ($this->action)($request);

        $body = $response->getBody();
        $this->assertSame('wf-old', $body['data'][0]['workflow_id']);
        $this->assertSame('wf-new', $body['data'][1]['workflow_id']);
    }

    #[Test]
    public function it_includes_summary_fields_in_list_items(): void
    {
        $execId = $this->eventStore->createExecution('OrderWorkflow', 'wf-1', 'run-1', ['amount' => 50]);
        $this->eventStore->appendEvent(
            $execId,
            WorkflowEvent::workflowStarted(1, ['workflowType' => 'OrderWorkflow']),
        );
        $this->eventStore->appendEvent(
            $execId,
            WorkflowEvent::activityCompleted(2, 'act-1', 'result'),
        );

        $request = new Request('GET', '/api/chronos/workflows');
        $response = ($this->action)($request);

        $body = $response->getBody();
        $item = $body['data'][0];

        $this->assertArrayHasKey('id', $item);
        $this->assertArrayHasKey('workflow_id', $item);
        $this->assertArrayHasKey('type', $item);
        $this->assertArrayHasKey('status', $item);
        $this->assertArrayHasKey('started_at', $item);
        $this->assertArrayHasKey('duration_ms', $item);
        $this->assertArrayHasKey('last_event_type', $item);
        $this->assertArrayHasKey('last_event_at', $item);

        $this->assertSame('OrderWorkflow', $item['type']);
        $this->assertSame('activity_completed', $item['last_event_type']);
    }

    #[Test]
    public function it_returns_correct_response_envelope(): void
    {
        $request = new Request('GET', '/api/chronos/workflows');
        $response = ($this->action)($request);

        $body = $response->getBody();
        $this->assertArrayHasKey('data', $body);
        $this->assertArrayHasKey('meta', $body);
        $this->assertArrayHasKey('page', $body['meta']);
        $this->assertArrayHasKey('per_page', $body['meta']);
        $this->assertArrayHasKey('total', $body['meta']);
        $this->assertArrayHasKey('has_more', $body['meta']);
    }

    private function seedWorkflows(int $count): void
    {
        for ($i = 1; $i <= $count; $i++) {
            $this->eventStore->createExecution(
                'TestWorkflow',
                "wf-{$i}",
                "run-{$i}",
                null,
            );
        }
    }
}
