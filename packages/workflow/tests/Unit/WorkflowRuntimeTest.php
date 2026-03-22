<?php

declare(strict_types=1);

namespace Lattice\Workflow\Tests\Unit;

use Lattice\Contracts\Workflow\WorkflowEventType;
use Lattice\Contracts\Workflow\WorkflowStatus;
use Lattice\Workflow\Registry\WorkflowRegistry;
use Lattice\Workflow\Runtime\SyncActivityExecutor;
use Lattice\Workflow\Runtime\WorkflowRuntime;
use Lattice\Workflow\Store\InMemoryEventStore;
use Lattice\Workflow\Tests\Fixtures\OrderFulfillmentWorkflow;
use Lattice\Workflow\Tests\Fixtures\PaymentActivity;
use Lattice\Workflow\Tests\Fixtures\ShippingActivity;
use Lattice\Workflow\Tests\Fixtures\SimpleWorkflow;
use Lattice\Workflow\Tests\Fixtures\TimerWorkflow;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class WorkflowRuntimeTest extends TestCase
{
    private InMemoryEventStore $eventStore;
    private SyncActivityExecutor $executor;
    private WorkflowRegistry $registry;
    private WorkflowRuntime $runtime;

    protected function setUp(): void
    {
        $this->eventStore = new InMemoryEventStore();
        $this->executor = new SyncActivityExecutor();
        $this->registry = new WorkflowRegistry();
        $this->runtime = new WorkflowRuntime(
            $this->eventStore,
            $this->executor,
            $this->registry,
        );
    }

    #[Test]
    public function it_starts_a_simple_workflow(): void
    {
        $executionId = $this->runtime->startWorkflow(
            SimpleWorkflow::class,
            'wf_simple_1',
            null,
            null,
        );

        $execution = $this->eventStore->getExecution($executionId);
        $this->assertNotNull($execution);
        $this->assertSame(WorkflowStatus::Completed, $execution->getStatus());
        $this->assertSame('simple_result', $execution->getResult());
    }

    #[Test]
    public function it_starts_a_workflow_with_activities(): void
    {
        $executionId = $this->runtime->startWorkflow(
            OrderFulfillmentWorkflow::class,
            'wf_order_1',
            ['amount' => 99.99, 'address' => '123 Main St'],
            null,
        );

        $execution = $this->eventStore->getExecution($executionId);
        $this->assertSame(WorkflowStatus::Completed, $execution->getStatus());

        $result = $execution->getResult();
        $this->assertIsArray($result);
        $this->assertArrayHasKey('payment', $result);
        $this->assertArrayHasKey('shipping', $result);
        $this->assertStringStartsWith('payment_', $result['payment']);
        $this->assertStringStartsWith('tracking_', $result['shipping']);
    }

    #[Test]
    public function it_records_all_events_for_workflow_with_activities(): void
    {
        $executionId = $this->runtime->startWorkflow(
            OrderFulfillmentWorkflow::class,
            'wf_order_2',
            ['amount' => 50.0, 'address' => '456 Oak Ave'],
            null,
        );

        $events = $this->eventStore->getEvents($executionId);

        // WorkflowStarted + (ActivityScheduled + ActivityStarted + ActivityCompleted) * 2 + WorkflowCompleted
        // = 1 + 6 + 1 = 8
        $this->assertCount(8, $events);

        $this->assertSame(WorkflowEventType::WorkflowStarted, $events[0]->getEventType());
        $this->assertSame(WorkflowEventType::ActivityScheduled, $events[1]->getEventType());
        $this->assertSame(WorkflowEventType::ActivityStarted, $events[2]->getEventType());
        $this->assertSame(WorkflowEventType::ActivityCompleted, $events[3]->getEventType());
        $this->assertSame(WorkflowEventType::ActivityScheduled, $events[4]->getEventType());
        $this->assertSame(WorkflowEventType::ActivityStarted, $events[5]->getEventType());
        $this->assertSame(WorkflowEventType::ActivityCompleted, $events[6]->getEventType());
        $this->assertSame(WorkflowEventType::WorkflowCompleted, $events[7]->getEventType());
    }

    #[Test]
    public function it_signals_a_running_workflow(): void
    {
        $executionId = $this->runtime->startWorkflow(
            OrderFulfillmentWorkflow::class,
            'wf_signal_test',
            ['amount' => 10.0, 'address' => '789 Pine Rd'],
            null,
        );

        // After completion, signal should still be recorded
        $this->runtime->signalWorkflow('wf_signal_test', 'markDelivered', null);

        $events = $this->eventStore->getEvents($executionId);
        $signalEvents = array_filter(
            $events,
            fn ($e) => $e->getEventType() === WorkflowEventType::SignalReceived,
        );

        $this->assertCount(1, $signalEvents);
    }

    #[Test]
    public function it_queries_workflow_state(): void
    {
        $this->runtime->startWorkflow(
            OrderFulfillmentWorkflow::class,
            'wf_query_test',
            ['amount' => 25.0, 'address' => '321 Elm Blvd'],
            null,
        );

        $status = $this->runtime->queryWorkflow('wf_query_test', 'getStatus');

        // After completion with two activities, status should be 'shipped'
        $this->assertSame('shipped', $status);
    }

    #[Test]
    public function it_cancels_a_workflow(): void
    {
        $executionId = $this->runtime->startWorkflow(
            SimpleWorkflow::class,
            'wf_cancel_test',
            null,
            null,
        );

        // Even though it completed, we can still mark it cancelled
        $this->runtime->cancelWorkflow('wf_cancel_test');

        $execution = $this->eventStore->getExecution($executionId);
        $this->assertSame(WorkflowStatus::Cancelled, $execution->getStatus());
    }

    #[Test]
    public function it_terminates_a_workflow(): void
    {
        $executionId = $this->runtime->startWorkflow(
            SimpleWorkflow::class,
            'wf_terminate_test',
            null,
            null,
        );

        $this->runtime->terminateWorkflow('wf_terminate_test', 'Admin request');

        $execution = $this->eventStore->getExecution($executionId);
        $this->assertSame(WorkflowStatus::Terminated, $execution->getStatus());

        $events = $this->eventStore->getEvents($executionId);
        $termEvents = array_filter(
            $events,
            fn ($e) => $e->getEventType() === WorkflowEventType::WorkflowTerminated,
        );
        $this->assertCount(1, $termEvents);
    }

    #[Test]
    public function it_throws_when_signaling_nonexistent_workflow(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->runtime->signalWorkflow('nonexistent', 'someSignal');
    }

    #[Test]
    public function it_throws_when_querying_nonexistent_workflow(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->runtime->queryWorkflow('nonexistent', 'someQuery');
    }

    #[Test]
    public function it_starts_workflow_with_timer(): void
    {
        $executionId = $this->runtime->startWorkflow(
            TimerWorkflow::class,
            'wf_timer_1',
            ['seconds' => 5],
            null,
        );

        $execution = $this->eventStore->getExecution($executionId);
        $this->assertSame(WorkflowStatus::Completed, $execution->getStatus());

        $result = $execution->getResult();
        $this->assertStringStartsWith('timer_done_', $result);

        // Verify timer events were recorded
        $events = $this->eventStore->getEvents($executionId);
        $timerEvents = array_filter(
            $events,
            fn ($e) => $e->getEventType() === WorkflowEventType::TimerStarted
                || $e->getEventType() === WorkflowEventType::TimerFired,
        );
        $this->assertCount(2, $timerEvents);
    }

    #[Test]
    public function it_resolves_workflow_by_registry(): void
    {
        $this->registry->registerWorkflow(SimpleWorkflow::class);

        $executionId = $this->runtime->startWorkflow(
            'SimpleWorkflow',
            'wf_registry_1',
            null,
            null,
        );

        $execution = $this->eventStore->getExecution($executionId);
        $this->assertSame(WorkflowStatus::Completed, $execution->getStatus());
        $this->assertSame('simple_result', $execution->getResult());
    }
}
