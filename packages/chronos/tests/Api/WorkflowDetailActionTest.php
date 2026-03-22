<?php

declare(strict_types=1);

namespace Lattice\Chronos\Tests\Api;

use Lattice\Chronos\Api\WorkflowDetailAction;
use Lattice\Chronos\InMemoryChronosEventStore;
use Lattice\Contracts\Workflow\WorkflowStatus;
use Lattice\Http\Request;
use Lattice\Workflow\Event\WorkflowEvent;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class WorkflowDetailActionTest extends TestCase
{
    private InMemoryChronosEventStore $eventStore;
    private WorkflowDetailAction $action;

    protected function setUp(): void
    {
        $this->eventStore = new InMemoryChronosEventStore();
        $this->action = new WorkflowDetailAction($this->eventStore);
    }

    #[Test]
    public function it_returns_workflow_detail_for_valid_id(): void
    {
        $execId = $this->eventStore->createExecution(
            'OrderWorkflow',
            'wf-order-1',
            'run-abc',
            ['amount' => 99.99],
        );

        $this->eventStore->appendEvent(
            $execId,
            WorkflowEvent::workflowStarted(1, ['workflowType' => 'OrderWorkflow']),
        );

        $request = new Request('GET', '/api/chronos/workflows/' . $execId, pathParams: ['id' => $execId]);
        $response = ($this->action)($request);

        $this->assertSame(200, $response->getStatusCode());

        $body = $response->getBody();
        $data = $body['data'];

        $this->assertSame($execId, $data['id']);
        $this->assertSame('wf-order-1', $data['workflow_id']);
        $this->assertSame('OrderWorkflow', $data['type']);
        $this->assertSame('run-abc', $data['run_id']);
        $this->assertSame('running', $data['status']);
        $this->assertSame(['amount' => 99.99], $data['input']);
        $this->assertNull($data['output']);
        $this->assertNotNull($data['started_at']);
        $this->assertNull($data['completed_at']);
        $this->assertNull($data['duration_ms']);
        $this->assertNull($data['parent_workflow_id']);
        $this->assertIsArray($data['events']);
        $this->assertCount(1, $data['events']);
        $this->assertSame(1, $data['total_events']);
        $this->assertFalse($data['has_more_events']);
    }

    #[Test]
    public function it_returns_404_for_unknown_id(): void
    {
        $request = new Request(
            'GET',
            '/api/chronos/workflows/nonexistent',
            pathParams: ['id' => 'nonexistent'],
        );
        $response = ($this->action)($request);

        $this->assertSame(404, $response->getStatusCode());

        $body = $response->getBody();
        $this->assertSame(404, $body['status']);
        $this->assertSame('Not Found', $body['title']);
        $this->assertStringContainsString('nonexistent', $body['detail']);
    }

    #[Test]
    public function it_returns_400_for_missing_id(): void
    {
        $request = new Request('GET', '/api/chronos/workflows/');
        $response = ($this->action)($request);

        $this->assertSame(400, $response->getStatusCode());
    }

    #[Test]
    public function it_includes_completed_workflow_detail(): void
    {
        $execId = $this->eventStore->createExecution('Workflow', 'wf-1', 'run-1', 'input-data');
        $this->eventStore->updateExecutionStatus($execId, WorkflowStatus::Completed, 'result-data');

        $request = new Request('GET', '/api/chronos/workflows/' . $execId, pathParams: ['id' => $execId]);
        $response = ($this->action)($request);

        $body = $response->getBody();
        $data = $body['data'];

        $this->assertSame('completed', $data['status']);
        $this->assertSame('result-data', $data['output']);
        $this->assertNotNull($data['completed_at']);
    }

    #[Test]
    public function it_includes_inline_events_up_to_limit(): void
    {
        $execId = $this->eventStore->createExecution('Workflow', 'wf-1', 'run-1', null);

        // Add 60 events (limit is 50)
        for ($i = 1; $i <= 60; $i++) {
            $this->eventStore->appendEvent(
                $execId,
                WorkflowEvent::activityScheduled($i, "act-{$i}", 'SomeActivity', 'execute', []),
            );
        }

        $request = new Request('GET', '/api/chronos/workflows/' . $execId, pathParams: ['id' => $execId]);
        $response = ($this->action)($request);

        $body = $response->getBody();
        $data = $body['data'];

        $this->assertCount(50, $data['events']);
        $this->assertTrue($data['has_more_events']);
        $this->assertSame(60, $data['total_events']);
    }

    #[Test]
    public function it_includes_event_detail_fields(): void
    {
        $execId = $this->eventStore->createExecution('Workflow', 'wf-1', 'run-1', null);
        $this->eventStore->appendEvent(
            $execId,
            WorkflowEvent::activityCompleted(1, 'act-1', ['payment' => 'success']),
        );

        $request = new Request('GET', '/api/chronos/workflows/' . $execId, pathParams: ['id' => $execId]);
        $response = ($this->action)($request);

        $body = $response->getBody();
        $event = $body['data']['events'][0];

        $this->assertSame(1, $event['sequence']);
        $this->assertSame('activity_completed', $event['type']);
        $this->assertNotNull($event['timestamp']);
        $this->assertIsArray($event['data']);
        $this->assertSame('act-1', $event['data']['activityId']);
    }

    #[Test]
    public function it_includes_response_structure(): void
    {
        $execId = $this->eventStore->createExecution('Workflow', 'wf-1', 'run-1', null);

        $request = new Request('GET', '/api/chronos/workflows/' . $execId, pathParams: ['id' => $execId]);
        $response = ($this->action)($request);

        $body = $response->getBody();

        $this->assertArrayHasKey('data', $body);
        $data = $body['data'];

        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('workflow_id', $data);
        $this->assertArrayHasKey('type', $data);
        $this->assertArrayHasKey('run_id', $data);
        $this->assertArrayHasKey('status', $data);
        $this->assertArrayHasKey('input', $data);
        $this->assertArrayHasKey('output', $data);
        $this->assertArrayHasKey('started_at', $data);
        $this->assertArrayHasKey('completed_at', $data);
        $this->assertArrayHasKey('duration_ms', $data);
        $this->assertArrayHasKey('parent_workflow_id', $data);
        $this->assertArrayHasKey('events', $data);
        $this->assertArrayHasKey('has_more_events', $data);
        $this->assertArrayHasKey('total_events', $data);
    }
}
