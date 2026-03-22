<?php

declare(strict_types=1);

namespace Lattice\Workflow\Tests\Unit;

use Lattice\Contracts\Workflow\WorkflowStatus;
use Lattice\Workflow\Event\WorkflowEvent;
use Lattice\Workflow\Store\InMemoryEventStore;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class InMemoryEventStoreTest extends TestCase
{
    private InMemoryEventStore $store;

    protected function setUp(): void
    {
        $this->store = new InMemoryEventStore();
    }

    #[Test]
    public function it_creates_an_execution(): void
    {
        $executionId = $this->store->createExecution(
            'OrderWorkflow',
            'wf_123',
            'run_abc',
            ['amount' => 100],
        );

        $this->assertNotEmpty($executionId);

        $execution = $this->store->getExecution($executionId);
        $this->assertNotNull($execution);
        $this->assertSame('OrderWorkflow', $execution->getWorkflowType());
        $this->assertSame('wf_123', $execution->getWorkflowId());
        $this->assertSame('run_abc', $execution->getRunId());
        $this->assertSame(['amount' => 100], $execution->getInput());
        $this->assertSame(WorkflowStatus::Running, $execution->getStatus());
    }

    #[Test]
    public function it_appends_and_retrieves_events(): void
    {
        $executionId = $this->store->createExecution('OrderWorkflow', 'wf_1', 'run_1', null);

        $event1 = WorkflowEvent::workflowStarted(1, ['workflowType' => 'OrderWorkflow']);
        $event2 = WorkflowEvent::activityScheduled(2, 'act_1', 'PaymentActivity', 'charge', [100]);

        $this->store->appendEvent($executionId, $event1);
        $this->store->appendEvent($executionId, $event2);

        $events = $this->store->getEvents($executionId);
        $this->assertCount(2, $events);
        $this->assertSame(1, $events[0]->getSequenceNumber());
        $this->assertSame(2, $events[1]->getSequenceNumber());
    }

    #[Test]
    public function it_returns_empty_array_for_unknown_execution_events(): void
    {
        $events = $this->store->getEvents('nonexistent');
        $this->assertSame([], $events);
    }

    #[Test]
    public function it_throws_when_appending_to_nonexistent_execution(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->store->appendEvent('nonexistent', WorkflowEvent::workflowStarted(1, []));
    }

    #[Test]
    public function it_updates_execution_status(): void
    {
        $executionId = $this->store->createExecution('OrderWorkflow', 'wf_1', 'run_1', null);

        $this->store->updateExecutionStatus($executionId, WorkflowStatus::Completed, 'done');

        $execution = $this->store->getExecution($executionId);
        $this->assertSame(WorkflowStatus::Completed, $execution->getStatus());
        $this->assertSame('done', $execution->getResult());
    }

    #[Test]
    public function it_throws_when_updating_nonexistent_execution(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->store->updateExecutionStatus('nonexistent', WorkflowStatus::Completed);
    }

    #[Test]
    public function it_finds_execution_by_workflow_id(): void
    {
        $this->store->createExecution('TypeA', 'wf_alpha', 'run_1', null);
        $this->store->createExecution('TypeB', 'wf_beta', 'run_2', null);

        $found = $this->store->findExecutionByWorkflowId('wf_beta');
        $this->assertNotNull($found);
        $this->assertSame('wf_beta', $found->getWorkflowId());
        $this->assertSame('TypeB', $found->getWorkflowType());
    }

    #[Test]
    public function it_returns_null_for_unknown_workflow_id(): void
    {
        $this->assertNull($this->store->findExecutionByWorkflowId('unknown'));
    }

    #[Test]
    public function it_returns_null_for_unknown_execution_id(): void
    {
        $this->assertNull($this->store->getExecution('unknown'));
    }

    #[Test]
    public function it_generates_unique_execution_ids(): void
    {
        $id1 = $this->store->createExecution('A', 'wf_1', 'run_1', null);
        $id2 = $this->store->createExecution('B', 'wf_2', 'run_2', null);

        $this->assertNotSame($id1, $id2);
    }
}
