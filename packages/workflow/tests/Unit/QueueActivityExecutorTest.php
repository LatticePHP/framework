<?php

declare(strict_types=1);

namespace Lattice\Workflow\Tests\Unit;

use Lattice\Contracts\Workflow\WorkflowEventType;
use Lattice\Queue\Dispatcher;
use Lattice\Queue\Driver\InMemoryDriver;
use Lattice\Workflow\Event\WorkflowEvent;
use Lattice\Workflow\RetryPolicy;
use Lattice\Workflow\Runtime\ActivityFailedException;
use Lattice\Workflow\Runtime\ActivityJob;
use Lattice\Workflow\Runtime\QueueActivityExecutor;
use Lattice\Workflow\Store\InMemoryEventStore;
use Lattice\Workflow\Tests\Fixtures\FailingActivity;
use Lattice\Workflow\Tests\Fixtures\PaymentActivity;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class QueueActivityExecutorTest extends TestCase
{
    private InMemoryEventStore $eventStore;
    private InMemoryDriver $driver;
    private Dispatcher $dispatcher;
    private string $executionId;

    protected function setUp(): void
    {
        $this->eventStore = new InMemoryEventStore();
        $this->driver = new InMemoryDriver();
        $this->dispatcher = new Dispatcher($this->driver);
        $this->executionId = $this->eventStore->createExecution(
            'TestWorkflow',
            'wf_test',
            'run_test',
            null,
        );
    }

    private function createExecutor(): QueueActivityExecutor
    {
        return new QueueActivityExecutor(
            dispatcher: $this->dispatcher,
            eventStore: $this->eventStore,
            executionId: $this->executionId,
            pollIntervalMs: 1,
            timeoutSeconds: 5,
            jobProcessor: fn (ActivityJob $job) => $job->handle(),
        );
    }

    #[Test]
    public function it_dispatches_job_and_returns_result(): void
    {
        $executor = $this->createExecutor();

        // Write ActivityScheduled (as WorkflowContext would)
        $this->eventStore->appendEvent(
            $this->executionId,
            WorkflowEvent::activityScheduled(1, 'activity_1', PaymentActivity::class, 'charge', [100.0]),
        );

        $result = $executor->execute(PaymentActivity::class, 'charge', [100.0]);

        $this->assertStringStartsWith('payment_', $result);

        // Verify the events were written by the job
        $events = $this->eventStore->getEvents($this->executionId);
        $eventTypes = array_map(fn ($e) => $e->getEventType(), $events);

        $this->assertContains(WorkflowEventType::ActivityScheduled, $eventTypes);
        $this->assertContains(WorkflowEventType::ActivityStarted, $eventTypes);
        $this->assertContains(WorkflowEventType::ActivityCompleted, $eventTypes);
    }

    #[Test]
    public function it_throws_activity_failed_on_exception(): void
    {
        $executor = $this->createExecutor();

        // Write ActivityScheduled
        $this->eventStore->appendEvent(
            $this->executionId,
            WorkflowEvent::activityScheduled(1, 'activity_1', FailingActivity::class, 'alwaysFail', []),
        );

        $this->expectException(ActivityFailedException::class);
        $this->expectExceptionMessage('Permanent failure');

        $executor->execute(FailingActivity::class, 'alwaysFail', [], new RetryPolicy(maxAttempts: 1));
    }

    #[Test]
    public function it_throws_when_no_scheduled_event_exists(): void
    {
        $executor = $this->createExecutor();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No ActivityScheduled event found');

        $executor->execute(PaymentActivity::class, 'charge', [100.0]);
    }

    #[Test]
    public function it_works_with_multiple_activity_executions(): void
    {
        $executor = $this->createExecutor();

        // First activity
        $this->eventStore->appendEvent(
            $this->executionId,
            WorkflowEvent::activityScheduled(1, 'activity_1', PaymentActivity::class, 'charge', [100.0]),
        );

        $result1 = $executor->execute(PaymentActivity::class, 'charge', [100.0]);
        $this->assertStringStartsWith('payment_', $result1);

        // Second activity
        $this->eventStore->appendEvent(
            $this->executionId,
            WorkflowEvent::activityScheduled(10, 'activity_2', PaymentActivity::class, 'refund', ['pay_123']),
        );

        $result2 = $executor->execute(PaymentActivity::class, 'refund', ['pay_123']);
        $this->assertSame('refund_pay_123', $result2);
    }

    #[Test]
    public function it_writes_started_and_completed_events_with_correct_sequence(): void
    {
        $executor = $this->createExecutor();

        $this->eventStore->appendEvent(
            $this->executionId,
            WorkflowEvent::activityScheduled(5, 'activity_1', PaymentActivity::class, 'charge', [50.0]),
        );

        $executor->execute(PaymentActivity::class, 'charge', [50.0]);

        $events = $this->eventStore->getEvents($this->executionId);

        // Should have Scheduled (5), Started (6), Completed (7)
        $this->assertCount(3, $events);
        $this->assertSame(5, $events[0]->getSequenceNumber());
        $this->assertSame(6, $events[1]->getSequenceNumber());
        $this->assertSame(7, $events[2]->getSequenceNumber());
    }

    #[Test]
    public function it_writes_failed_event_with_error_details(): void
    {
        $executor = $this->createExecutor();

        $this->eventStore->appendEvent(
            $this->executionId,
            WorkflowEvent::activityScheduled(1, 'activity_1', FailingActivity::class, 'alwaysFail', []),
        );

        try {
            $executor->execute(FailingActivity::class, 'alwaysFail', [], new RetryPolicy(maxAttempts: 1));
            $this->fail('Expected exception');
        } catch (ActivityFailedException) {
            // expected
        }

        $events = $this->eventStore->getEvents($this->executionId);
        $failedEvents = array_filter(
            $events,
            fn ($e) => $e->getEventType() === WorkflowEventType::ActivityFailed,
        );

        $this->assertNotEmpty($failedEvents);
        $failedEvent = array_values($failedEvents)[0];
        $this->assertSame('Permanent failure', $failedEvent->getPayload()['error']);
        $this->assertSame(\RuntimeException::class, $failedEvent->getPayload()['errorClass']);
    }
}
