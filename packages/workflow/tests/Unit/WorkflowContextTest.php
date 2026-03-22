<?php

declare(strict_types=1);

namespace Lattice\Workflow\Tests\Unit;

use Lattice\Contracts\Workflow\WorkflowEventType;
use Lattice\Queue\Dispatcher;
use Lattice\Queue\Driver\InMemoryDriver;
use Lattice\Workflow\Event\WorkflowEvent;
use Lattice\Workflow\Runtime\ActivityJob;
use Lattice\Workflow\Runtime\QueueActivityExecutor;
use Lattice\Workflow\Runtime\ReplayCaughtUpException;
use Lattice\Workflow\Runtime\SyncActivityExecutor;
use Lattice\Workflow\Runtime\WorkflowContext;
use Lattice\Workflow\Store\InMemoryEventStore;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class WorkflowContextTest extends TestCase
{
    private InMemoryEventStore $eventStore;
    private SyncActivityExecutor $executor;
    private string $executionId;

    protected function setUp(): void
    {
        $this->eventStore = new InMemoryEventStore();
        $this->executor = new SyncActivityExecutor();
        $this->executionId = $this->eventStore->createExecution(
            'TestWorkflow',
            'wf_test',
            'run_test',
            null,
        );
    }

    private function createContext(): WorkflowContext
    {
        return new WorkflowContext(
            $this->executionId,
            'wf_test',
            'run_test',
            $this->eventStore,
            $this->executor,
        );
    }

    #[Test]
    public function it_returns_workflow_id_and_run_id(): void
    {
        $ctx = $this->createContext();

        $this->assertSame('wf_test', $ctx->getWorkflowId());
        $this->assertSame('run_test', $ctx->getRunId());
    }

    #[Test]
    public function it_defaults_to_not_replaying(): void
    {
        $ctx = $this->createContext();

        $this->assertFalse($ctx->isReplaying());
    }

    #[Test]
    public function it_can_be_set_to_replay_mode(): void
    {
        $ctx = $this->createContext();

        $ctx->setReplaying(true);
        $this->assertTrue($ctx->isReplaying());

        $ctx->setReplaying(false);
        $this->assertFalse($ctx->isReplaying());
    }

    #[Test]
    public function it_executes_activity_in_live_mode_and_records_events(): void
    {
        $ctx = $this->createContext();

        $result = $ctx->executeActivity(
            \Lattice\Workflow\Tests\Fixtures\PaymentActivity::class,
            'charge',
            100.0,
        );

        $this->assertNotEmpty($result);
        $this->assertStringStartsWith('payment_', $result);

        // Verify events were recorded
        $events = $this->eventStore->getEvents($this->executionId);
        $this->assertCount(3, $events); // Scheduled, Started, Completed

        $this->assertSame(WorkflowEventType::ActivityScheduled, $events[0]->getEventType());
        $this->assertSame(WorkflowEventType::ActivityStarted, $events[1]->getEventType());
        $this->assertSame(WorkflowEventType::ActivityCompleted, $events[2]->getEventType());
    }

    #[Test]
    public function it_returns_recorded_result_during_replay(): void
    {
        $ctx = $this->createContext();

        // Set up replay events simulating a completed activity
        $replayEvents = [
            WorkflowEvent::workflowStarted(1, ['workflowType' => 'Test']),
            WorkflowEvent::activityScheduled(2, 'activity_1', 'PaymentActivity', 'charge', [100.0]),
            WorkflowEvent::activityStarted(3, 'activity_1'),
            WorkflowEvent::activityCompleted(4, 'activity_1', 'payment_recorded_123'),
        ];

        $ctx->setReplaying(true);
        $ctx->loadReplayEvents($replayEvents);

        $result = $ctx->executeActivity(
            \Lattice\Workflow\Tests\Fixtures\PaymentActivity::class,
            'charge',
            100.0,
        );

        // Should return the recorded result, NOT execute the real activity
        $this->assertSame('payment_recorded_123', $result);

        // No new events should be recorded during replay
        $events = $this->eventStore->getEvents($this->executionId);
        $this->assertCount(0, $events);
    }

    #[Test]
    public function it_switches_to_live_mode_when_replay_events_exhausted(): void
    {
        $ctx = $this->createContext();

        // Load only the workflow started event — no activity events
        $replayEvents = [
            WorkflowEvent::workflowStarted(1, ['workflowType' => 'Test']),
        ];

        $ctx->setReplaying(true);
        $ctx->loadReplayEvents($replayEvents);

        // When replay has no activity events, executeActivity should seamlessly
        // switch to live mode and execute the activity (not throw)
        $result = $ctx->executeActivity(
            \Lattice\Workflow\Tests\Fixtures\PaymentActivity::class,
            'charge',
            100.0,
        );

        // The activity should have executed live
        $this->assertStringStartsWith('payment_', $result);
        $this->assertFalse($ctx->isReplaying());

        // Events should have been recorded in the store
        $events = $this->eventStore->getEvents($this->executionId);
        $this->assertNotEmpty($events);
    }

    #[Test]
    public function it_records_timer_events_in_live_mode(): void
    {
        $ctx = $this->createContext();

        $ctx->sleep(30);

        $events = $this->eventStore->getEvents($this->executionId);
        $this->assertCount(2, $events);
        $this->assertSame(WorkflowEventType::TimerStarted, $events[0]->getEventType());
        $this->assertSame(30, $events[0]->getPayload()['durationSeconds']);
        $this->assertSame(WorkflowEventType::TimerFired, $events[1]->getEventType());
    }

    #[Test]
    public function it_replays_timers_from_history(): void
    {
        $ctx = $this->createContext();

        $replayEvents = [
            WorkflowEvent::timerStarted(1, 'timer_1', 30),
            WorkflowEvent::timerFired(2, 'timer_1'),
        ];

        $ctx->setReplaying(true);
        $ctx->loadReplayEvents($replayEvents);

        // Should not throw — timer was already fired in history
        $ctx->sleep(30);

        // No new events recorded
        $events = $this->eventStore->getEvents($this->executionId);
        $this->assertCount(0, $events);
    }

    #[Test]
    public function it_collects_pending_signals(): void
    {
        $ctx = $this->createContext();

        $replayEvents = [
            WorkflowEvent::workflowStarted(1, []),
            WorkflowEvent::signalReceived(2, 'markDelivered', null),
            WorkflowEvent::signalReceived(3, 'updateStatus', 'shipped'),
        ];

        $ctx->loadReplayEvents($replayEvents);

        $signals = $ctx->collectPendingSignals();
        $this->assertCount(2, $signals);
        $this->assertSame('markDelivered', $signals[0]->getPayload()['signalName']);
        $this->assertSame('updateStatus', $signals[1]->getPayload()['signalName']);
    }

    #[Test]
    public function it_evaluates_await_condition_synchronously(): void
    {
        $ctx = $this->createContext();

        $result = $ctx->awaitCondition(fn () => true);
        $this->assertTrue($result);

        $result = $ctx->awaitCondition(fn () => false);
        $this->assertFalse($result);
    }

    // --- Activity driver tests ---

    #[Test]
    public function it_defaults_to_sync_activity_driver(): void
    {
        $ctx = $this->createContext();

        $this->assertSame('sync', $ctx->getActivityDriver());
    }

    #[Test]
    public function it_accepts_queue_activity_driver(): void
    {
        $ctx = new WorkflowContext(
            $this->executionId,
            'wf_test',
            'run_test',
            $this->eventStore,
            $this->executor,
            null,
            'queue',
        );

        $this->assertSame('queue', $ctx->getActivityDriver());
    }

    #[Test]
    public function it_uses_sync_executor_when_driver_is_sync(): void
    {
        $ctx = $this->createContext();

        $result = $ctx->executeActivity(
            \Lattice\Workflow\Tests\Fixtures\PaymentActivity::class,
            'charge',
            100.0,
        );

        $this->assertStringStartsWith('payment_', $result);

        // Sync path records Scheduled, Started, Completed
        $events = $this->eventStore->getEvents($this->executionId);
        $this->assertCount(3, $events);
        $this->assertSame(WorkflowEventType::ActivityScheduled, $events[0]->getEventType());
        $this->assertSame(WorkflowEventType::ActivityStarted, $events[1]->getEventType());
        $this->assertSame(WorkflowEventType::ActivityCompleted, $events[2]->getEventType());
    }

    #[Test]
    public function it_uses_queue_executor_when_driver_is_queue(): void
    {
        $driver = new InMemoryDriver();
        $dispatcher = new Dispatcher($driver);

        $queueExecutor = new QueueActivityExecutor(
            dispatcher: $dispatcher,
            eventStore: $this->eventStore,
            executionId: $this->executionId,
            pollIntervalMs: 1,
            timeoutSeconds: 5,
            jobProcessor: fn (ActivityJob $job) => $job->handle(),
        );

        $ctx = new WorkflowContext(
            $this->executionId,
            'wf_test',
            'run_test',
            $this->eventStore,
            $this->executor,
            null,
            'queue',
        );
        $ctx->setQueueActivityExecutor($queueExecutor);

        $result = $ctx->executeActivity(
            \Lattice\Workflow\Tests\Fixtures\PaymentActivity::class,
            'charge',
            100.0,
        );

        $this->assertStringStartsWith('payment_', $result);

        // Queue path: context writes Scheduled, job writes Started + Completed
        $events = $this->eventStore->getEvents($this->executionId);
        $eventTypes = array_map(fn ($e) => $e->getEventType(), $events);

        $this->assertContains(WorkflowEventType::ActivityScheduled, $eventTypes);
        $this->assertContains(WorkflowEventType::ActivityStarted, $eventTypes);
        $this->assertContains(WorkflowEventType::ActivityCompleted, $eventTypes);
    }

    #[Test]
    public function it_falls_back_to_sync_executor_when_queue_executor_not_set(): void
    {
        // When driver is 'queue' but no queue executor is set, falls back to sync executor
        $ctx = new WorkflowContext(
            $this->executionId,
            'wf_test',
            'run_test',
            $this->eventStore,
            $this->executor,
            null,
            'queue',
        );

        $result = $ctx->executeActivity(
            \Lattice\Workflow\Tests\Fixtures\PaymentActivity::class,
            'charge',
            100.0,
        );

        $this->assertStringStartsWith('payment_', $result);
    }
}
