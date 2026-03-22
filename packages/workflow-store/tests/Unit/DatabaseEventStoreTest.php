<?php

declare(strict_types=1);

namespace Lattice\WorkflowStore\Tests\Unit;

use Lattice\Contracts\Workflow\WorkflowEventType;
use Lattice\Contracts\Workflow\WorkflowStatus;
use Lattice\Workflow\Event\WorkflowEvent;
use Lattice\WorkflowStore\DatabaseEventStore;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DatabaseEventStoreTest extends TestCase
{
    private \PDO $pdo;
    private DatabaseEventStore $store;

    protected function setUp(): void
    {
        $this->pdo = new \PDO('sqlite::memory:');
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->store = new DatabaseEventStore($this->pdo);
        $this->store->ensureSchema();
    }

    #[Test]
    public function test_create_execution_returns_id(): void
    {
        $executionId = $this->store->createExecution(
            workflowType: 'OrderWorkflow',
            workflowId: 'order-123',
            runId: 'run-1',
            input: ['orderId' => 123],
        );

        $this->assertNotEmpty($executionId);
        $this->assertIsString($executionId);
    }

    #[Test]
    public function test_get_execution_returns_created_execution(): void
    {
        $executionId = $this->store->createExecution(
            workflowType: 'OrderWorkflow',
            workflowId: 'order-123',
            runId: 'run-1',
            input: ['orderId' => 123],
        );

        $execution = $this->store->getExecution($executionId);

        $this->assertNotNull($execution);
        $this->assertSame($executionId, $execution->getId());
        $this->assertSame('OrderWorkflow', $execution->getWorkflowType());
        $this->assertSame('order-123', $execution->getWorkflowId());
        $this->assertSame('run-1', $execution->getRunId());
        $this->assertSame(['orderId' => 123], $execution->getInput());
        $this->assertSame(WorkflowStatus::Running, $execution->getStatus());
        $this->assertNull($execution->getResult());
        $this->assertNull($execution->getCompletedAt());
        $this->assertNull($execution->getParentWorkflowId());
    }

    #[Test]
    public function test_get_execution_returns_null_for_unknown_id(): void
    {
        $execution = $this->store->getExecution('nonexistent');

        $this->assertNull($execution);
    }

    #[Test]
    public function test_find_execution_by_workflow_id(): void
    {
        $executionId = $this->store->createExecution(
            workflowType: 'OrderWorkflow',
            workflowId: 'order-456',
            runId: 'run-1',
            input: null,
        );

        $execution = $this->store->findExecutionByWorkflowId('order-456');

        $this->assertNotNull($execution);
        $this->assertSame($executionId, $execution->getId());
    }

    #[Test]
    public function test_find_execution_by_workflow_id_returns_null_when_not_found(): void
    {
        $execution = $this->store->findExecutionByWorkflowId('nonexistent');

        $this->assertNull($execution);
    }

    #[Test]
    public function test_update_execution_status(): void
    {
        $executionId = $this->store->createExecution(
            workflowType: 'OrderWorkflow',
            workflowId: 'order-789',
            runId: 'run-1',
            input: null,
        );

        $this->store->updateExecutionStatus($executionId, WorkflowStatus::Completed, ['success' => true]);

        $execution = $this->store->getExecution($executionId);

        $this->assertNotNull($execution);
        $this->assertSame(WorkflowStatus::Completed, $execution->getStatus());
        $this->assertSame(['success' => true], $execution->getResult());
    }

    #[Test]
    public function test_update_execution_status_throws_for_unknown_id(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Execution not found');

        $this->store->updateExecutionStatus('nonexistent', WorkflowStatus::Failed);
    }

    #[Test]
    public function test_append_and_get_events(): void
    {
        $executionId = $this->store->createExecution(
            workflowType: 'OrderWorkflow',
            workflowId: 'order-100',
            runId: 'run-1',
            input: ['orderId' => 100],
        );

        $event1 = WorkflowEvent::workflowStarted(1, ['input' => ['orderId' => 100]]);
        $event2 = WorkflowEvent::activityScheduled(2, 'act-1', 'PaymentActivity', 'charge', [100]);
        $event3 = WorkflowEvent::activityCompleted(3, 'act-1', 'paid');

        $this->store->appendEvent($executionId, $event1);
        $this->store->appendEvent($executionId, $event2);
        $this->store->appendEvent($executionId, $event3);

        $events = $this->store->getEvents($executionId);

        $this->assertCount(3, $events);
        $this->assertSame(WorkflowEventType::WorkflowStarted, $events[0]->getEventType());
        $this->assertSame(1, $events[0]->getSequenceNumber());
        $this->assertSame(WorkflowEventType::ActivityScheduled, $events[1]->getEventType());
        $this->assertSame(2, $events[1]->getSequenceNumber());
        $this->assertSame(WorkflowEventType::ActivityCompleted, $events[2]->getEventType());
        $this->assertSame(3, $events[2]->getSequenceNumber());
    }

    #[Test]
    public function test_events_returned_in_sequence_order(): void
    {
        $executionId = $this->store->createExecution(
            workflowType: 'OrderWorkflow',
            workflowId: 'order-200',
            runId: 'run-1',
            input: null,
        );

        // Insert out of order
        $event3 = WorkflowEvent::activityCompleted(3, 'act-1', 'done');
        $event1 = WorkflowEvent::workflowStarted(1, []);
        $event2 = WorkflowEvent::activityScheduled(2, 'act-1', 'SomeActivity', 'run', []);

        $this->store->appendEvent($executionId, $event3);
        $this->store->appendEvent($executionId, $event1);
        $this->store->appendEvent($executionId, $event2);

        $events = $this->store->getEvents($executionId);

        $this->assertCount(3, $events);
        $this->assertSame(1, $events[0]->getSequenceNumber());
        $this->assertSame(2, $events[1]->getSequenceNumber());
        $this->assertSame(3, $events[2]->getSequenceNumber());
    }

    #[Test]
    public function test_append_event_throws_for_unknown_execution(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Execution not found');

        $event = WorkflowEvent::workflowStarted(1, []);
        $this->store->appendEvent('nonexistent', $event);
    }

    #[Test]
    public function test_get_events_returns_empty_for_no_events(): void
    {
        $executionId = $this->store->createExecution(
            workflowType: 'EmptyWorkflow',
            workflowId: 'empty-1',
            runId: 'run-1',
            input: null,
        );

        $events = $this->store->getEvents($executionId);

        $this->assertSame([], $events);
    }

    #[Test]
    public function test_execution_survives_store_and_retrieve_durability(): void
    {
        // Create execution
        $executionId = $this->store->createExecution(
            workflowType: 'DurableWorkflow',
            workflowId: 'durable-1',
            runId: 'run-durable',
            input: ['key' => 'value', 'nested' => ['a' => 1]],
        );

        // Add events
        $this->store->appendEvent($executionId, WorkflowEvent::workflowStarted(1, ['input' => ['key' => 'value']]));
        $this->store->appendEvent($executionId, WorkflowEvent::activityScheduled(2, 'act-1', 'ProcessActivity', 'process', ['data']));
        $this->store->appendEvent($executionId, WorkflowEvent::activityCompleted(3, 'act-1', ['processed' => true]));
        $this->store->appendEvent($executionId, WorkflowEvent::workflowCompleted(4, 'all-done'));

        // Update status
        $this->store->updateExecutionStatus($executionId, WorkflowStatus::Completed, 'all-done');

        // Create a brand new store instance pointing to the same DB (simulates restart)
        $store2 = new DatabaseEventStore($this->pdo);

        // Retrieve execution from new store
        $execution = $store2->getExecution($executionId);

        $this->assertNotNull($execution);
        $this->assertSame('DurableWorkflow', $execution->getWorkflowType());
        $this->assertSame('durable-1', $execution->getWorkflowId());
        $this->assertSame('run-durable', $execution->getRunId());
        $this->assertSame(['key' => 'value', 'nested' => ['a' => 1]], $execution->getInput());
        $this->assertSame(WorkflowStatus::Completed, $execution->getStatus());
        $this->assertSame('all-done', $execution->getResult());

        // Retrieve events from new store
        $events = $store2->getEvents($executionId);

        $this->assertCount(4, $events);
        $this->assertSame(WorkflowEventType::WorkflowStarted, $events[0]->getEventType());
        $this->assertSame(WorkflowEventType::ActivityScheduled, $events[1]->getEventType());
        $this->assertSame(WorkflowEventType::ActivityCompleted, $events[2]->getEventType());
        $this->assertSame(WorkflowEventType::WorkflowCompleted, $events[3]->getEventType());

        // Verify event payloads survived
        $this->assertSame(['processed' => true], $events[2]->getPayload()['result']);
    }

    #[Test]
    public function test_find_execution_by_workflow_id_returns_latest(): void
    {
        // Create two executions with same workflow ID (different runs)
        $this->store->createExecution(
            workflowType: 'RetryWorkflow',
            workflowId: 'retry-1',
            runId: 'run-1',
            input: null,
        );

        // Small delay to ensure different started_at (SQLite datetime precision)
        $exec2 = $this->store->createExecution(
            workflowType: 'RetryWorkflow',
            workflowId: 'retry-1',
            runId: 'run-2',
            input: null,
        );

        $found = $this->store->findExecutionByWorkflowId('retry-1');

        $this->assertNotNull($found);
        // Should return the latest (most recent started_at)
        $this->assertSame($exec2, $found->getId());
    }

    #[Test]
    public function test_update_status_without_result(): void
    {
        $executionId = $this->store->createExecution(
            workflowType: 'FailWorkflow',
            workflowId: 'fail-1',
            runId: 'run-1',
            input: null,
        );

        $this->store->updateExecutionStatus($executionId, WorkflowStatus::Failed);

        $execution = $this->store->getExecution($executionId);

        $this->assertNotNull($execution);
        $this->assertSame(WorkflowStatus::Failed, $execution->getStatus());
        $this->assertNull($execution->getResult());
    }

    #[Test]
    public function test_ensure_schema_is_idempotent(): void
    {
        // Already called in setUp, call again - should not throw
        $this->store->ensureSchema();
        $this->store->ensureSchema();

        // Should still work
        $executionId = $this->store->createExecution(
            workflowType: 'Test',
            workflowId: 'test-1',
            runId: 'run-1',
            input: null,
        );

        $this->assertNotEmpty($executionId);
    }
}
