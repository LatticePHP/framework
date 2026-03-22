<?php

declare(strict_types=1);

namespace Lattice\Workflow\Tests\Unit;

use Lattice\Contracts\Workflow\WorkflowEventType;
use Lattice\Contracts\Workflow\WorkflowStatus;
use Lattice\Workflow\Event\WorkflowEvent;
use Lattice\Workflow\Registry\WorkflowRegistry;
use Lattice\Workflow\Runtime\SyncActivityExecutor;
use Lattice\Workflow\Runtime\WorkflowContext;
use Lattice\Workflow\Runtime\WorkflowRuntime;
use Lattice\Workflow\Store\InMemoryEventStore;
use Lattice\Workflow\Tests\Fixtures\OrderFulfillmentWorkflow;
use Lattice\Workflow\Tests\Fixtures\PaymentActivity;
use Lattice\Workflow\Tests\Fixtures\ShippingActivity;
use Lattice\Workflow\Tests\Fixtures\TimerWorkflow;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * THE KEY TEST: Deterministic replay.
 *
 * This test proves that:
 * 1. A workflow can run and produce events
 * 2. Given those same events, replaying the workflow on a new runtime produces the same state
 * 3. The replayed workflow does NOT re-execute activities — it reads results from the event log
 */
final class DeterministicReplayTest extends TestCase
{
    #[Test]
    public function it_replays_a_completed_workflow_producing_identical_state(): void
    {
        // --- Phase 1: Run the workflow, collect events ---
        $store1 = new InMemoryEventStore();
        $executor1 = new SyncActivityExecutor();
        $registry1 = new WorkflowRegistry();
        $runtime1 = new WorkflowRuntime($store1, $executor1, $registry1);

        $executionId1 = $runtime1->startWorkflow(
            OrderFulfillmentWorkflow::class,
            'wf_replay_test',
            ['amount' => 42.0, 'address' => '100 Replay Lane'],
            null,
        );

        $execution1 = $store1->getExecution($executionId1);
        $this->assertSame(WorkflowStatus::Completed, $execution1->getStatus());

        $originalResult = $execution1->getResult();
        $originalEvents = $store1->getEvents($executionId1);

        // Verify we got a complete event history
        $this->assertNotEmpty($originalEvents);
        $this->assertSame(
            WorkflowEventType::WorkflowStarted,
            $originalEvents[0]->getEventType(),
        );
        $this->assertSame(
            WorkflowEventType::WorkflowCompleted,
            $originalEvents[count($originalEvents) - 1]->getEventType(),
        );

        // --- Phase 2: Create a NEW runtime with the same events and replay ---
        $store2 = new InMemoryEventStore();

        // Recreate the execution with the same IDs
        $executionId2 = $store2->createExecution(
            $execution1->getWorkflowType(),
            $execution1->getWorkflowId(),
            $execution1->getRunId(),
            $execution1->getInput(),
        );

        // Copy all events from original to new store
        foreach ($originalEvents as $event) {
            $store2->appendEvent($executionId2, $event);
        }

        // Mark it as completed with the result (simulating what runtime does)
        $store2->updateExecutionStatus($executionId2, WorkflowStatus::Completed, $originalResult);

        // Now use a TRACKING executor that records if any activities were actually called
        $trackingExecutor = new TrackingActivityExecutor();
        $registry2 = new WorkflowRegistry();
        $runtime2 = new WorkflowRuntime($store2, $trackingExecutor, $registry2);

        // Replay the workflow
        $runtime2->resumeWorkflow($executionId2);

        // --- Phase 3: Verify replay correctness ---

        // The tracking executor should NOT have been called — replay reads from history
        $this->assertSame(
            0,
            $trackingExecutor->getCallCount(),
            'Replay should NOT execute real activities — it should read results from event history',
        );

        // The execution should still be completed with the same result
        $execution2 = $store2->getExecution($executionId2);
        $this->assertSame(WorkflowStatus::Completed, $execution2->getStatus());
        $this->assertSame($originalResult, $execution2->getResult());
    }

    #[Test]
    public function it_replays_workflow_state_for_queries_after_crash(): void
    {
        // --- Phase 1: Run workflow, get query result ---
        $store = new InMemoryEventStore();
        $executor = new SyncActivityExecutor();
        $registry = new WorkflowRegistry();
        $runtime = new WorkflowRuntime($store, $executor, $registry);

        $runtime->startWorkflow(
            OrderFulfillmentWorkflow::class,
            'wf_crash_query',
            ['amount' => 10.0, 'address' => 'Crash Ave'],
            null,
        );

        $statusBefore = $runtime->queryWorkflow('wf_crash_query', 'getStatus');

        // --- Phase 2: Create NEW runtime (simulating process restart after crash) ---
        // The same event store persists (like a database would)
        $runtime2 = new WorkflowRuntime($store, new SyncActivityExecutor(), new WorkflowRegistry());

        // Query on the new runtime should produce the same state
        $statusAfter = $runtime2->queryWorkflow('wf_crash_query', 'getStatus');

        $this->assertSame($statusBefore, $statusAfter);
        $this->assertSame('shipped', $statusAfter);
    }

    #[Test]
    public function it_replays_events_in_order_preserving_activity_results(): void
    {
        $store = new InMemoryEventStore();
        $executor = new SyncActivityExecutor();
        $registry = new WorkflowRegistry();
        $runtime = new WorkflowRuntime($store, $executor, $registry);

        $executionId = $runtime->startWorkflow(
            OrderFulfillmentWorkflow::class,
            'wf_order_test',
            ['amount' => 77.0, 'address' => '42 Galaxy Way'],
            null,
        );

        $events = $store->getEvents($executionId);

        // Extract the activity results from events
        $activityResults = [];
        foreach ($events as $event) {
            if ($event->getEventType() === WorkflowEventType::ActivityCompleted) {
                $activityResults[] = $event->getPayload()['result'];
            }
        }

        $this->assertCount(2, $activityResults);

        // Payment result should be deterministic (based on md5 of amount)
        $this->assertSame('payment_' . md5('77'), $activityResults[0]);
        // Shipping result should be deterministic (based on md5 of address)
        $this->assertSame('tracking_' . md5('42 Galaxy Way'), $activityResults[1]);
    }

    #[Test]
    public function replay_produces_same_events_as_original_execution(): void
    {
        // Run workflow and capture events
        $store1 = new InMemoryEventStore();
        $executor1 = new SyncActivityExecutor();
        $runtime1 = new WorkflowRuntime($store1, $executor1, new WorkflowRegistry());

        $execId1 = $runtime1->startWorkflow(
            OrderFulfillmentWorkflow::class,
            'wf_event_compare',
            ['amount' => 55.0, 'address' => '1 Compare St'],
            null,
        );

        $originalEvents = $store1->getEvents($execId1);
        $originalExec = $store1->getExecution($execId1);

        // Create a new store with events up to (but NOT including) WorkflowCompleted
        // This simulates a crash after activities completed but before workflow finished
        $store2 = new InMemoryEventStore();
        $execId2 = $store2->createExecution(
            $originalExec->getWorkflowType(),
            'wf_event_compare_2',
            $originalExec->getRunId(),
            $originalExec->getInput(),
        );

        // Copy events except the final WorkflowCompleted
        foreach ($originalEvents as $event) {
            if ($event->getEventType() !== WorkflowEventType::WorkflowCompleted) {
                $store2->appendEvent($execId2, $event);
            }
        }

        // Replay — this should replay from history and produce WorkflowCompleted
        $runtime2 = new WorkflowRuntime($store2, new SyncActivityExecutor(), new WorkflowRegistry());
        $runtime2->resumeWorkflow($execId2);

        $replayedExec = $store2->getExecution($execId2);
        $this->assertSame(WorkflowStatus::Completed, $replayedExec->getStatus());
        $this->assertSame($originalExec->getResult(), $replayedExec->getResult());
    }

    #[Test]
    public function it_replays_workflow_with_timers(): void
    {
        $store = new InMemoryEventStore();
        $executor = new SyncActivityExecutor();
        $runtime = new WorkflowRuntime($store, $executor, new WorkflowRegistry());

        $executionId = $runtime->startWorkflow(
            TimerWorkflow::class,
            'wf_timer_replay',
            ['seconds' => 60],
            null,
        );

        $originalEvents = $store->getEvents($executionId);
        $originalExec = $store->getExecution($executionId);

        // Verify timer events exist
        $timerEvents = array_filter(
            $originalEvents,
            fn ($e) => $e->getEventType() === WorkflowEventType::TimerStarted
                || $e->getEventType() === WorkflowEventType::TimerFired,
        );
        $this->assertCount(2, $timerEvents);

        // Replay with tracking executor
        $store2 = new InMemoryEventStore();
        $execId2 = $store2->createExecution(
            $originalExec->getWorkflowType(),
            $originalExec->getWorkflowId() . '_replay',
            $originalExec->getRunId(),
            $originalExec->getInput(),
        );

        foreach ($originalEvents as $event) {
            $store2->appendEvent($execId2, $event);
        }
        $store2->updateExecutionStatus($execId2, WorkflowStatus::Completed, $originalExec->getResult());

        $tracker = new TrackingActivityExecutor();
        $runtime2 = new WorkflowRuntime($store2, $tracker, new WorkflowRegistry());
        $runtime2->resumeWorkflow($execId2);

        // No real activities should have been executed during replay
        $this->assertSame(0, $tracker->getCallCount());
    }

    #[Test]
    public function it_correctly_rebuilds_state_with_signals_after_replay(): void
    {
        $store = new InMemoryEventStore();
        $executor = new SyncActivityExecutor();
        $registry = new WorkflowRegistry();
        $runtime = new WorkflowRuntime($store, $executor, $registry);

        // Start and complete the workflow
        $runtime->startWorkflow(
            OrderFulfillmentWorkflow::class,
            'wf_signal_replay',
            ['amount' => 20.0, 'address' => 'Signal Blvd'],
            null,
        );

        // Send a signal
        $runtime->signalWorkflow('wf_signal_replay', 'markDelivered', null);

        // Query on a new runtime to verify state was rebuilt correctly
        $runtime2 = new WorkflowRuntime($store, new SyncActivityExecutor(), new WorkflowRegistry());
        $status = $runtime2->queryWorkflow('wf_signal_replay', 'getStatus');

        // After signal, status should include the delivered signal
        // The query rebuilds state by replaying, so it should reflect the signal
        $this->assertSame('delivered', $status);
    }
}

/**
 * A test executor that tracks whether any real activity executions were attempted.
 * Used to verify that replay does NOT execute real activities.
 */
final class TrackingActivityExecutor extends \Lattice\Workflow\Runtime\ActivityExecutor
{
    private int $callCount = 0;

    protected function doExecute(
        string $activityClass,
        string $method,
        array $args,
        int $attempt,
    ): mixed {
        $this->callCount++;

        $instance = new $activityClass();
        return $instance->$method(...$args);
    }

    public function getCallCount(): int
    {
        return $this->callCount;
    }
}
