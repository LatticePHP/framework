<?php

declare(strict_types=1);

namespace Tests\Integration;

use Lattice\Contracts\Workflow\WorkflowEventType;
use Lattice\Contracts\Workflow\WorkflowStatus;
use Lattice\Workflow\Testing\WorkflowTestEnvironment;
use Tests\Integration\Fixtures\GreetingActivity;
use Tests\Integration\Fixtures\GreetingWorkflow;

final class WorkflowTest extends TestCase
{
    private WorkflowTestEnvironment $env;

    protected function setUp(): void
    {
        parent::setUp();

        $this->env = new WorkflowTestEnvironment();
        $this->env->registerWorkflow(GreetingWorkflow::class);
        $this->env->registerActivity(GreetingActivity::class);
    }

    public function test_runs_simple_workflow_end_to_end(): void
    {
        $handle = $this->env->startWorkflow('GreetingWorkflow', 'Alice');

        $this->env->assertWorkflowStarted('GreetingWorkflow');

        $execution = $this->env->getEventStore()->getExecution(
            $this->env->getEventStore()->findExecutionByWorkflowId(
                $handle->getWorkflowId(),
            )->getId(),
        );

        $this->assertSame(WorkflowStatus::Completed, $execution->getStatus());
        $this->assertSame('Hello, Alice! Goodbye, Alice!', $execution->getResult());
    }

    public function test_events_are_recorded_correctly(): void
    {
        $handle = $this->env->startWorkflow('GreetingWorkflow', 'Bob');

        $execution = $this->env->getEventStore()->findExecutionByWorkflowId(
            $handle->getWorkflowId(),
        );
        $events = $this->env->getEventStore()->getEvents($execution->getId());

        $eventTypes = array_map(
            fn ($e) => $e->getEventType(),
            $events,
        );

        // Should have: WorkflowStarted, ActivityScheduled, ActivityStarted,
        // ActivityCompleted (for compose), ActivityScheduled, ActivityStarted,
        // ActivityCompleted (for farewell), WorkflowCompleted
        $this->assertContains(WorkflowEventType::WorkflowStarted, $eventTypes);
        $this->assertContains(WorkflowEventType::ActivityScheduled, $eventTypes);
        $this->assertContains(WorkflowEventType::ActivityStarted, $eventTypes);
        $this->assertContains(WorkflowEventType::ActivityCompleted, $eventTypes);
        $this->assertContains(WorkflowEventType::WorkflowCompleted, $eventTypes);
    }

    public function test_activity_results_are_stored_in_events(): void
    {
        $handle = $this->env->startWorkflow('GreetingWorkflow', 'Charlie');

        $execution = $this->env->getEventStore()->findExecutionByWorkflowId(
            $handle->getWorkflowId(),
        );
        $events = $this->env->getEventStore()->getEvents($execution->getId());

        $completedEvents = array_values(array_filter(
            $events,
            fn ($e) => $e->getEventType() === WorkflowEventType::ActivityCompleted,
        ));

        $this->assertCount(2, $completedEvents);
        $this->assertSame('Hello, Charlie!', $completedEvents[0]->getPayload()['result']);
        $this->assertSame('Goodbye, Charlie!', $completedEvents[1]->getPayload()['result']);
    }

    public function test_workflow_started_event_records_input(): void
    {
        $handle = $this->env->startWorkflow('GreetingWorkflow', 'Dana');

        $execution = $this->env->getEventStore()->findExecutionByWorkflowId(
            $handle->getWorkflowId(),
        );
        $events = $this->env->getEventStore()->getEvents($execution->getId());

        $startedEvent = null;
        foreach ($events as $event) {
            if ($event->getEventType() === WorkflowEventType::WorkflowStarted) {
                $startedEvent = $event;
                break;
            }
        }

        $this->assertNotNull($startedEvent);
        $this->assertSame('GreetingWorkflow', $startedEvent->getPayload()['workflowType']);
        $this->assertSame('Dana', $startedEvent->getPayload()['input']);
    }

    public function test_workflow_events_have_sequential_numbers(): void
    {
        $handle = $this->env->startWorkflow('GreetingWorkflow', 'Eve');

        $execution = $this->env->getEventStore()->findExecutionByWorkflowId(
            $handle->getWorkflowId(),
        );
        $events = $this->env->getEventStore()->getEvents($execution->getId());

        $sequenceNumbers = array_map(
            fn ($e) => $e->getSequenceNumber(),
            $events,
        );

        // Verify sequence numbers are monotonically increasing
        for ($i = 1; $i < count($sequenceNumbers); $i++) {
            $this->assertGreaterThan(
                $sequenceNumbers[$i - 1],
                $sequenceNumbers[$i],
                'Event sequence numbers should be monotonically increasing',
            );
        }
    }

    public function test_multiple_workflows_are_isolated(): void
    {
        $handle1 = $this->env->startWorkflow('GreetingWorkflow', 'Frank');
        $handle2 = $this->env->startWorkflow('GreetingWorkflow', 'Grace');

        $exec1 = $this->env->getEventStore()->findExecutionByWorkflowId($handle1->getWorkflowId());
        $exec2 = $this->env->getEventStore()->findExecutionByWorkflowId($handle2->getWorkflowId());

        $this->assertSame('Hello, Frank! Goodbye, Frank!', $exec1->getResult());
        $this->assertSame('Hello, Grace! Goodbye, Grace!', $exec2->getResult());
        $this->assertNotSame($exec1->getId(), $exec2->getId());
    }
}
