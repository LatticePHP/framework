<?php

declare(strict_types=1);

namespace Lattice\Workflow\Tests\Unit;

use DateTimeImmutable;
use Lattice\Contracts\Workflow\WorkflowEventType;
use Lattice\Workflow\Event\WorkflowEvent;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class WorkflowEventTest extends TestCase
{
    #[Test]
    public function it_creates_a_workflow_started_event(): void
    {
        $event = WorkflowEvent::workflowStarted(1, [
            'workflowType' => 'OrderWorkflow',
            'input' => ['amount' => 100],
        ]);

        $this->assertSame(WorkflowEventType::WorkflowStarted, $event->getEventType());
        $this->assertSame(1, $event->getSequenceNumber());
        $this->assertSame('OrderWorkflow', $event->getPayload()['workflowType']);
        $this->assertInstanceOf(DateTimeImmutable::class, $event->getTimestamp());
    }

    #[Test]
    public function it_creates_a_workflow_completed_event(): void
    {
        $event = WorkflowEvent::workflowCompleted(5, ['order' => 'confirmed']);

        $this->assertSame(WorkflowEventType::WorkflowCompleted, $event->getEventType());
        $this->assertSame(5, $event->getSequenceNumber());
        $this->assertSame(['order' => 'confirmed'], $event->getPayload()['result']);
    }

    #[Test]
    public function it_creates_a_workflow_failed_event(): void
    {
        $event = WorkflowEvent::workflowFailed(3, 'Something went wrong', \RuntimeException::class);

        $this->assertSame(WorkflowEventType::WorkflowFailed, $event->getEventType());
        $this->assertSame('Something went wrong', $event->getPayload()['error']);
        $this->assertSame(\RuntimeException::class, $event->getPayload()['errorClass']);
    }

    #[Test]
    public function it_creates_an_activity_scheduled_event(): void
    {
        $event = WorkflowEvent::activityScheduled(
            2,
            'activity_1',
            'PaymentActivity',
            'charge',
            [100.0],
        );

        $this->assertSame(WorkflowEventType::ActivityScheduled, $event->getEventType());
        $this->assertSame('activity_1', $event->getPayload()['activityId']);
        $this->assertSame('PaymentActivity', $event->getPayload()['activityClass']);
        $this->assertSame('charge', $event->getPayload()['method']);
        $this->assertSame([100.0], $event->getPayload()['args']);
    }

    #[Test]
    public function it_creates_an_activity_completed_event(): void
    {
        $event = WorkflowEvent::activityCompleted(4, 'activity_1', 'payment_abc123');

        $this->assertSame(WorkflowEventType::ActivityCompleted, $event->getEventType());
        $this->assertSame('activity_1', $event->getPayload()['activityId']);
        $this->assertSame('payment_abc123', $event->getPayload()['result']);
    }

    #[Test]
    public function it_creates_an_activity_failed_event(): void
    {
        $event = WorkflowEvent::activityFailed(4, 'activity_1', 'Timeout', \RuntimeException::class);

        $this->assertSame(WorkflowEventType::ActivityFailed, $event->getEventType());
        $this->assertSame('activity_1', $event->getPayload()['activityId']);
        $this->assertSame('Timeout', $event->getPayload()['error']);
    }

    #[Test]
    public function it_creates_timer_events(): void
    {
        $started = WorkflowEvent::timerStarted(2, 'timer_1', 30);
        $this->assertSame(WorkflowEventType::TimerStarted, $started->getEventType());
        $this->assertSame(30, $started->getPayload()['durationSeconds']);

        $fired = WorkflowEvent::timerFired(3, 'timer_1');
        $this->assertSame(WorkflowEventType::TimerFired, $fired->getEventType());
        $this->assertSame('timer_1', $fired->getPayload()['timerId']);

        $cancelled = WorkflowEvent::timerCancelled(3, 'timer_1');
        $this->assertSame(WorkflowEventType::TimerCancelled, $cancelled->getEventType());
    }

    #[Test]
    public function it_creates_signal_received_event(): void
    {
        $event = WorkflowEvent::signalReceived(6, 'markDelivered', ['trackingId' => 'ABC']);

        $this->assertSame(WorkflowEventType::SignalReceived, $event->getEventType());
        $this->assertSame('markDelivered', $event->getPayload()['signalName']);
        $this->assertSame(['trackingId' => 'ABC'], $event->getPayload()['payload']);
    }

    #[Test]
    public function it_creates_child_workflow_events(): void
    {
        $started = WorkflowEvent::childWorkflowStarted(7, 'child_1', 'SubWorkflow');
        $this->assertSame(WorkflowEventType::ChildWorkflowStarted, $started->getEventType());
        $this->assertSame('child_1', $started->getPayload()['childWorkflowId']);

        $completed = WorkflowEvent::childWorkflowCompleted(8, 'child_1', 'child_result');
        $this->assertSame(WorkflowEventType::ChildWorkflowCompleted, $completed->getEventType());
        $this->assertSame('child_result', $completed->getPayload()['result']);

        $failed = WorkflowEvent::childWorkflowFailed(8, 'child_1', 'Child failed');
        $this->assertSame(WorkflowEventType::ChildWorkflowFailed, $failed->getEventType());
        $this->assertSame('Child failed', $failed->getPayload()['error']);
    }

    #[Test]
    public function it_creates_update_received_event(): void
    {
        $event = WorkflowEvent::updateReceived(10, 'updateAddress', '123 New St');

        $this->assertSame(WorkflowEventType::UpdateReceived, $event->getEventType());
        $this->assertSame('updateAddress', $event->getPayload()['updateName']);
        $this->assertSame('123 New St', $event->getPayload()['payload']);
    }

    #[Test]
    public function it_creates_workflow_cancelled_event(): void
    {
        $event = WorkflowEvent::workflowCancelled(5);

        $this->assertSame(WorkflowEventType::WorkflowCancelled, $event->getEventType());
        $this->assertSame(5, $event->getSequenceNumber());
    }

    #[Test]
    public function it_creates_workflow_terminated_event(): void
    {
        $event = WorkflowEvent::workflowTerminated(5, 'Admin terminated');

        $this->assertSame(WorkflowEventType::WorkflowTerminated, $event->getEventType());
        $this->assertSame('Admin terminated', $event->getPayload()['reason']);
    }
}
