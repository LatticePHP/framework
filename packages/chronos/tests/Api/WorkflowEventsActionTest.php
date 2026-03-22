<?php

declare(strict_types=1);

namespace Lattice\Chronos\Tests\Api;

use Lattice\Chronos\Api\WorkflowEventsAction;
use Lattice\Chronos\InMemoryChronosEventStore;
use Lattice\Http\Request;
use Lattice\Workflow\Event\WorkflowEvent;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class WorkflowEventsActionTest extends TestCase
{
    private InMemoryChronosEventStore $eventStore;
    private WorkflowEventsAction $action;

    protected function setUp(): void
    {
        $this->eventStore = new InMemoryChronosEventStore();
        $this->action = new WorkflowEventsAction($this->eventStore);
    }

    #[Test]
    public function it_returns_paginated_events(): void
    {
        $execId = $this->createExecutionWithEvents(10);

        $request = new Request(
            'GET',
            "/api/chronos/workflows/{$execId}/events",
            query: ['page' => '1', 'per_page' => '3'],
            pathParams: ['id' => $execId],
        );
        $response = ($this->action)($request);

        $this->assertSame(200, $response->getStatusCode());

        $body = $response->getBody();
        $this->assertCount(3, $body['data']);
        $this->assertSame(10, $body['meta']['total']);
        $this->assertSame(1, $body['meta']['page']);
        $this->assertSame(3, $body['meta']['per_page']);
        $this->assertTrue($body['meta']['has_more']);
    }

    #[Test]
    public function it_returns_last_page_correctly(): void
    {
        $execId = $this->createExecutionWithEvents(5);

        $request = new Request(
            'GET',
            "/api/chronos/workflows/{$execId}/events",
            query: ['page' => '2', 'per_page' => '3'],
            pathParams: ['id' => $execId],
        );
        $response = ($this->action)($request);

        $body = $response->getBody();
        $this->assertCount(2, $body['data']);
        $this->assertFalse($body['meta']['has_more']);
    }

    #[Test]
    public function it_filters_by_event_type(): void
    {
        $execId = $this->eventStore->createExecution('Workflow', 'wf-1', 'run-1', null);
        $this->eventStore->appendEvent($execId, WorkflowEvent::workflowStarted(1, []));
        $this->eventStore->appendEvent($execId, WorkflowEvent::activityScheduled(2, 'act-1', 'SomeActivity', 'run', []));
        $this->eventStore->appendEvent($execId, WorkflowEvent::activityStarted(3, 'act-1'));
        $this->eventStore->appendEvent($execId, WorkflowEvent::activityCompleted(4, 'act-1', 'done'));
        $this->eventStore->appendEvent($execId, WorkflowEvent::workflowCompleted(5, 'result'));

        $request = new Request(
            'GET',
            "/api/chronos/workflows/{$execId}/events",
            query: ['event_type' => 'activity_completed,workflow_completed'],
            pathParams: ['id' => $execId],
        );
        $response = ($this->action)($request);

        $body = $response->getBody();
        $this->assertCount(2, $body['data']);
        $this->assertSame('activity_completed', $body['data'][0]['type']);
        $this->assertSame('workflow_completed', $body['data'][1]['type']);
    }

    #[Test]
    public function it_returns_events_in_ascending_order_by_default(): void
    {
        $execId = $this->createExecutionWithEvents(3);

        $request = new Request(
            'GET',
            "/api/chronos/workflows/{$execId}/events",
            pathParams: ['id' => $execId],
        );
        $response = ($this->action)($request);

        $body = $response->getBody();
        $sequences = array_column($body['data'], 'sequence');
        $this->assertSame([1, 2, 3], $sequences);
    }

    #[Test]
    public function it_supports_descending_order(): void
    {
        $execId = $this->createExecutionWithEvents(3);

        $request = new Request(
            'GET',
            "/api/chronos/workflows/{$execId}/events",
            query: ['order' => 'desc'],
            pathParams: ['id' => $execId],
        );
        $response = ($this->action)($request);

        $body = $response->getBody();
        $sequences = array_column($body['data'], 'sequence');
        $this->assertSame([3, 2, 1], $sequences);
    }

    #[Test]
    public function it_returns_404_for_unknown_execution(): void
    {
        $request = new Request(
            'GET',
            '/api/chronos/workflows/nonexistent/events',
            pathParams: ['id' => 'nonexistent'],
        );
        $response = ($this->action)($request);

        $this->assertSame(404, $response->getStatusCode());
    }

    #[Test]
    public function it_returns_400_for_missing_id(): void
    {
        $request = new Request('GET', '/api/chronos/workflows//events');
        $response = ($this->action)($request);

        $this->assertSame(400, $response->getStatusCode());
    }

    #[Test]
    public function it_includes_event_fields(): void
    {
        $execId = $this->eventStore->createExecution('Workflow', 'wf-1', 'run-1', null);
        $this->eventStore->appendEvent(
            $execId,
            WorkflowEvent::activityCompleted(1, 'act-1', ['payment' => 'ok']),
        );

        $request = new Request(
            'GET',
            "/api/chronos/workflows/{$execId}/events",
            pathParams: ['id' => $execId],
        );
        $response = ($this->action)($request);

        $body = $response->getBody();
        $event = $body['data'][0];

        $this->assertArrayHasKey('sequence', $event);
        $this->assertArrayHasKey('type', $event);
        $this->assertArrayHasKey('timestamp', $event);
        $this->assertArrayHasKey('data', $event);
        $this->assertArrayHasKey('duration_ms', $event);

        $this->assertSame(1, $event['sequence']);
        $this->assertSame('activity_completed', $event['type']);
    }

    #[Test]
    public function it_returns_empty_events_for_execution_with_no_events(): void
    {
        $execId = $this->eventStore->createExecution('Workflow', 'wf-1', 'run-1', null);

        $request = new Request(
            'GET',
            "/api/chronos/workflows/{$execId}/events",
            pathParams: ['id' => $execId],
        );
        $response = ($this->action)($request);

        $body = $response->getBody();
        $this->assertSame([], $body['data']);
        $this->assertSame(0, $body['meta']['total']);
    }

    private function createExecutionWithEvents(int $eventCount): string
    {
        $execId = $this->eventStore->createExecution('Workflow', 'wf-1', 'run-1', null);

        for ($i = 1; $i <= $eventCount; $i++) {
            $this->eventStore->appendEvent(
                $execId,
                WorkflowEvent::activityScheduled($i, "act-{$i}", 'SomeActivity', 'execute', []),
            );
        }

        return $execId;
    }
}
