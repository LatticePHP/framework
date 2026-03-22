<?php

declare(strict_types=1);

namespace Lattice\Chronos\Tests\Api;

use Lattice\Chronos\Api\WorkflowSignalAction;
use Lattice\Chronos\InMemoryChronosEventStore;
use Lattice\Contracts\Workflow\WorkflowEventType;
use Lattice\Contracts\Workflow\WorkflowStatus;
use Lattice\Http\Request;
use Lattice\Workflow\Event\WorkflowEvent;
use Lattice\Workflow\Registry\WorkflowRegistry;
use Lattice\Workflow\Runtime\SyncActivityExecutor;
use Lattice\Workflow\Runtime\WorkflowRuntime;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class WorkflowSignalActionTest extends TestCase
{
    private InMemoryChronosEventStore $eventStore;
    private WorkflowRuntime $runtime;
    private WorkflowSignalAction $action;

    protected function setUp(): void
    {
        $this->eventStore = new InMemoryChronosEventStore();
        $registry = new WorkflowRegistry();
        $executor = new SyncActivityExecutor();
        $this->runtime = new WorkflowRuntime($this->eventStore, $executor, $registry);
        $this->action = new WorkflowSignalAction($this->eventStore, $this->runtime);
    }

    #[Test]
    public function it_sends_signal_to_a_completed_workflow(): void
    {
        // Create a workflow manually and mark it completed
        $execId = $this->eventStore->createExecution('OrderWorkflow', 'wf-signal-1', 'run-1', null);
        $this->eventStore->appendEvent(
            $execId,
            WorkflowEvent::workflowStarted(1, ['workflowType' => 'OrderWorkflow']),
        );
        $this->eventStore->updateExecutionStatus($execId, WorkflowStatus::Completed, 'done');

        $request = new Request(
            'POST',
            "/api/chronos/workflows/{$execId}/signal",
            body: ['signal' => 'markDelivered', 'payload' => ['note' => 'left at door']],
            pathParams: ['id' => $execId],
        );
        $response = ($this->action)($request);

        $this->assertSame(200, $response->getStatusCode());

        $body = $response->getBody();
        $this->assertSame($execId, $body['data']['id']);
        $this->assertSame('markDelivered', $body['data']['signal']);
        $this->assertSame('delivered', $body['data']['status']);

        // Verify signal event was recorded
        $events = $this->eventStore->getEvents($execId);
        $signalEvents = array_filter(
            $events,
            fn ($e) => $e->getEventType() === WorkflowEventType::SignalReceived,
        );
        $this->assertCount(1, $signalEvents);
    }

    #[Test]
    public function it_rejects_signal_for_cancelled_workflow(): void
    {
        $execId = $this->eventStore->createExecution('Workflow', 'wf-1', 'run-1', null);
        $this->eventStore->updateExecutionStatus($execId, WorkflowStatus::Cancelled);

        $request = new Request(
            'POST',
            "/api/chronos/workflows/{$execId}/signal",
            body: ['signal' => 'test'],
            pathParams: ['id' => $execId],
        );
        $response = ($this->action)($request);

        $this->assertSame(409, $response->getStatusCode());
        $body = $response->getBody();
        $this->assertStringContainsString('cancelled', $body['detail']);
    }

    #[Test]
    public function it_rejects_signal_for_terminated_workflow(): void
    {
        $execId = $this->eventStore->createExecution('Workflow', 'wf-1', 'run-1', null);
        $this->eventStore->updateExecutionStatus($execId, WorkflowStatus::Terminated);

        $request = new Request(
            'POST',
            "/api/chronos/workflows/{$execId}/signal",
            body: ['signal' => 'test'],
            pathParams: ['id' => $execId],
        );
        $response = ($this->action)($request);

        $this->assertSame(409, $response->getStatusCode());
    }

    #[Test]
    public function it_returns_404_for_unknown_execution(): void
    {
        $request = new Request(
            'POST',
            '/api/chronos/workflows/nonexistent/signal',
            body: ['signal' => 'test'],
            pathParams: ['id' => 'nonexistent'],
        );
        $response = ($this->action)($request);

        $this->assertSame(404, $response->getStatusCode());
    }

    #[Test]
    public function it_returns_422_for_missing_signal_field(): void
    {
        $execId = $this->eventStore->createExecution('Workflow', 'wf-1', 'run-1', null);

        $request = new Request(
            'POST',
            "/api/chronos/workflows/{$execId}/signal",
            body: ['payload' => 'data'],
            pathParams: ['id' => $execId],
        );
        $response = ($this->action)($request);

        $this->assertSame(422, $response->getStatusCode());
    }

    #[Test]
    public function it_returns_422_for_null_body(): void
    {
        $execId = $this->eventStore->createExecution('Workflow', 'wf-1', 'run-1', null);

        $request = new Request(
            'POST',
            "/api/chronos/workflows/{$execId}/signal",
            body: null,
            pathParams: ['id' => $execId],
        );
        $response = ($this->action)($request);

        $this->assertSame(422, $response->getStatusCode());
    }

    #[Test]
    public function it_returns_400_for_missing_execution_id(): void
    {
        $request = new Request(
            'POST',
            '/api/chronos/workflows//signal',
            body: ['signal' => 'test'],
        );
        $response = ($this->action)($request);

        $this->assertSame(400, $response->getStatusCode());
    }
}
