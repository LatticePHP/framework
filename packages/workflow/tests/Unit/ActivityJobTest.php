<?php

declare(strict_types=1);

namespace Lattice\Workflow\Tests\Unit;

use Lattice\Contracts\Workflow\WorkflowEventType;
use Lattice\Workflow\Runtime\ActivityJob;
use Lattice\Workflow\Store\InMemoryEventStore;
use Lattice\Workflow\Tests\Fixtures\FailingActivity;
use Lattice\Workflow\Tests\Fixtures\PaymentActivity;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ActivityJobTest extends TestCase
{
    private InMemoryEventStore $eventStore;
    private string $executionId;

    protected function setUp(): void
    {
        $this->eventStore = new InMemoryEventStore();
        $this->executionId = $this->eventStore->createExecution(
            'TestWorkflow',
            'wf_test',
            'run_test',
            null,
        );
    }

    #[Test]
    public function it_executes_activity_and_writes_completed_event(): void
    {
        $job = new ActivityJob(
            executionId: $this->executionId,
            activityId: 'activity_1',
            activityClass: PaymentActivity::class,
            method: 'charge',
            args: [100.0],
            scheduledSequenceNumber: 1,
            startedSequenceNumber: 2,
        );
        $job->setEventStore($this->eventStore);

        $job->handle();

        $events = $this->eventStore->getEvents($this->executionId);
        $this->assertCount(2, $events);

        $this->assertSame(WorkflowEventType::ActivityStarted, $events[0]->getEventType());
        $this->assertSame('activity_1', $events[0]->getPayload()['activityId']);
        $this->assertSame(2, $events[0]->getSequenceNumber());

        $this->assertSame(WorkflowEventType::ActivityCompleted, $events[1]->getEventType());
        $this->assertSame('activity_1', $events[1]->getPayload()['activityId']);
        $this->assertSame(3, $events[1]->getSequenceNumber());
        $this->assertStringStartsWith('payment_', $events[1]->getPayload()['result']);
    }

    #[Test]
    public function it_writes_failed_event_on_exception(): void
    {
        $job = new ActivityJob(
            executionId: $this->executionId,
            activityId: 'activity_2',
            activityClass: FailingActivity::class,
            method: 'alwaysFail',
            args: [],
            scheduledSequenceNumber: 1,
            startedSequenceNumber: 2,
        );
        $job->setEventStore($this->eventStore);

        try {
            $job->handle();
            $this->fail('Expected exception was not thrown');
        } catch (\RuntimeException $e) {
            $this->assertSame('Permanent failure', $e->getMessage());
        }

        $events = $this->eventStore->getEvents($this->executionId);
        $this->assertCount(2, $events);

        $this->assertSame(WorkflowEventType::ActivityStarted, $events[0]->getEventType());
        $this->assertSame(WorkflowEventType::ActivityFailed, $events[1]->getEventType());
        $this->assertSame('Permanent failure', $events[1]->getPayload()['error']);
        $this->assertSame(\RuntimeException::class, $events[1]->getPayload()['errorClass']);
    }

    #[Test]
    public function it_throws_without_event_store(): void
    {
        $job = new ActivityJob(
            executionId: $this->executionId,
            activityId: 'activity_3',
            activityClass: PaymentActivity::class,
            method: 'charge',
            args: [50.0],
            scheduledSequenceNumber: 1,
            startedSequenceNumber: 2,
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('requires an event store');

        $job->handle();
    }

    #[Test]
    public function it_exposes_job_properties(): void
    {
        $job = new ActivityJob(
            executionId: 'exec_42',
            activityId: 'activity_7',
            activityClass: PaymentActivity::class,
            method: 'charge',
            args: [99.0],
            scheduledSequenceNumber: 10,
            startedSequenceNumber: 11,
        );

        $this->assertSame('exec_42', $job->getExecutionId());
        $this->assertSame('activity_7', $job->getActivityId());
        $this->assertSame(PaymentActivity::class, $job->getActivityClass());
        $this->assertSame('charge', $job->getMethod());
        $this->assertSame([99.0], $job->getArgs());
    }

    #[Test]
    public function it_extends_abstract_job_with_defaults(): void
    {
        $job = new ActivityJob(
            executionId: $this->executionId,
            activityId: 'activity_1',
            activityClass: PaymentActivity::class,
            method: 'charge',
            args: [10.0],
            scheduledSequenceNumber: 1,
            startedSequenceNumber: 2,
        );

        $this->assertSame('default', $job->getQueue());
        $this->assertSame('default', $job->getConnection());
        $this->assertSame(3, $job->getMaxAttempts());
        $this->assertSame(60, $job->getTimeout());
    }

    #[Test]
    public function it_throws_for_nonexistent_method(): void
    {
        $job = new ActivityJob(
            executionId: $this->executionId,
            activityId: 'activity_1',
            activityClass: PaymentActivity::class,
            method: 'nonExistentMethod',
            args: [],
            scheduledSequenceNumber: 1,
            startedSequenceNumber: 2,
        );
        $job->setEventStore($this->eventStore);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('does not exist');

        $job->handle();
    }

    #[Test]
    public function failed_method_writes_activity_failed_event(): void
    {
        $job = new ActivityJob(
            executionId: $this->executionId,
            activityId: 'activity_1',
            activityClass: PaymentActivity::class,
            method: 'charge',
            args: [10.0],
            scheduledSequenceNumber: 1,
            startedSequenceNumber: 2,
        );
        $job->setEventStore($this->eventStore);

        $exception = new \RuntimeException('External failure');
        $job->failed($exception);

        $events = $this->eventStore->getEvents($this->executionId);
        $this->assertCount(1, $events);
        $this->assertSame(WorkflowEventType::ActivityFailed, $events[0]->getEventType());
        $this->assertSame('External failure', $events[0]->getPayload()['error']);
    }
}
