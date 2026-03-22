<?php

declare(strict_types=1);

namespace Lattice\Workflow\Tests\Integration;

use Lattice\Contracts\Workflow\WorkflowStatus;
use Lattice\Workflow\Attributes\Activity;
use Lattice\Workflow\Attributes\QueryMethod;
use Lattice\Workflow\Attributes\SignalMethod;
use Lattice\Workflow\Attributes\Workflow;
use Lattice\Workflow\Client\WorkflowClient;
use Lattice\Workflow\Compensation\CompensationScope;
use Lattice\Workflow\Registry\WorkflowRegistry;
use Lattice\Workflow\Runtime\SyncActivityExecutor;
use Lattice\Workflow\Runtime\WorkflowContext;
use Lattice\Workflow\Runtime\WorkflowRuntime;
use Lattice\Workflow\Store\InMemoryEventStore;
use Lattice\Workflow\Testing\WorkflowTestEnvironment;
use Lattice\Workflow\WorkflowOptions;
use PHPUnit\Framework\TestCase;

// ---------------------------------------------------------------------------
// Inline test workflow fixtures
// ---------------------------------------------------------------------------

#[Workflow(name: 'GreetingWorkflow')]
final class GreetingWorkflow
{
    public function execute(WorkflowContext $ctx, string $name): string
    {
        $greeting = $ctx->executeActivity(GreetingActivity::class, 'greet', $name);

        return $greeting;
    }
}

#[Activity(name: 'GreetingActivity')]
final class GreetingActivity
{
    public function greet(string $name): string
    {
        return "Hello, {$name}!";
    }
}

#[Workflow(name: 'MultiStepWorkflow')]
final class MultiStepWorkflow
{
    public function execute(WorkflowContext $ctx, array $input): array
    {
        $step1 = $ctx->executeActivity(StepActivity::class, 'step', 'first', $input['value']);
        $step2 = $ctx->executeActivity(StepActivity::class, 'step', 'second', $step1);

        return ['step1' => $step1, 'step2' => $step2];
    }
}

#[Activity(name: 'StepActivity')]
final class StepActivity
{
    public function step(string $phase, string $input): string
    {
        return "{$phase}:{$input}";
    }
}

#[Workflow(name: 'SignalQueryWorkflow')]
final class SignalQueryWorkflow
{
    private string $status = 'initial';
    private int $counter = 0;

    public function execute(WorkflowContext $ctx): string
    {
        $this->status = 'running';

        $result = $ctx->executeActivity(StepActivity::class, 'step', 'main', 'data');
        $this->status = 'activity_done';

        return $result;
    }

    #[SignalMethod(name: 'increment')]
    public function increment(mixed $payload = null): void
    {
        $this->counter++;
        $this->status = 'incremented_' . $this->counter;
    }

    #[QueryMethod(name: 'getStatus')]
    public function getStatus(): string
    {
        return $this->status;
    }

    #[QueryMethod(name: 'getCounter')]
    public function getCounter(): int
    {
        return $this->counter;
    }
}

#[Workflow(name: 'SagaCompensationWorkflow')]
final class SagaCompensationWorkflow
{
    public function execute(WorkflowContext $ctx, array $input): array
    {
        $scope = new CompensationScope();
        $results = [];

        $results['charge'] = $scope->run(
            fn () => $ctx->executeActivity(OrderActivity::class, 'charge', $input['amount']),
            fn () => $ctx->executeActivity(OrderActivity::class, 'refund', $results['charge'] ?? ''),
        );

        $results['reserve'] = $scope->run(
            fn () => $ctx->executeActivity(OrderActivity::class, 'reserveInventory', $input['item']),
            fn () => $ctx->executeActivity(OrderActivity::class, 'releaseInventory', $results['reserve'] ?? ''),
        );

        if ($input['fail'] ?? false) {
            // Trigger compensation
            $scope->compensate();
        }

        return $results;
    }
}

#[Activity(name: 'OrderActivity')]
final class OrderActivity
{
    /** @var list<string> Tracks executed actions for verification */
    public static array $executedActions = [];

    public function charge(float $amount): string
    {
        $id = 'charge_' . md5((string) $amount);
        self::$executedActions[] = "charge:{$amount}";
        return $id;
    }

    public function refund(string $chargeId): string
    {
        self::$executedActions[] = "refund:{$chargeId}";
        return 'refunded_' . $chargeId;
    }

    public function reserveInventory(string $item): string
    {
        $id = 'reserve_' . md5($item);
        self::$executedActions[] = "reserve:{$item}";
        return $id;
    }

    public function releaseInventory(string $reserveId): string
    {
        self::$executedActions[] = "release:{$reserveId}";
        return 'released_' . $reserveId;
    }
}

#[Workflow(name: 'FullCycleWorkflow')]
final class FullCycleWorkflow
{
    private string $state = 'created';
    private array $log = [];

    public function execute(WorkflowContext $ctx, array $input): array
    {
        $this->state = 'processing';
        $this->log[] = 'started';

        $stepResult = $ctx->executeActivity(StepActivity::class, 'step', 'process', $input['data']);
        $this->log[] = 'activity_completed';
        $this->state = 'processed';

        return [
            'result' => $stepResult,
            'log' => $this->log,
        ];
    }

    #[SignalMethod(name: 'addNote')]
    public function addNote(mixed $note = null): void
    {
        $this->log[] = 'note:' . ($note ?? '');
        $this->state = 'noted';
    }

    #[QueryMethod(name: 'getState')]
    public function getState(): string
    {
        return $this->state;
    }

    #[QueryMethod(name: 'getLog')]
    public function getLog(): array
    {
        return $this->log;
    }
}

// ---------------------------------------------------------------------------
// Integration test class
// ---------------------------------------------------------------------------

final class WorkflowIntegrationTest extends TestCase
{
    private InMemoryEventStore $eventStore;
    private SyncActivityExecutor $activityExecutor;
    private WorkflowRegistry $registry;
    private WorkflowRuntime $runtime;
    private WorkflowClient $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->eventStore = new InMemoryEventStore();
        $this->activityExecutor = new SyncActivityExecutor();
        $this->registry = new WorkflowRegistry();
        $this->runtime = new WorkflowRuntime(
            $this->eventStore,
            $this->activityExecutor,
            $this->registry,
        );
        $this->client = new WorkflowClient($this->runtime, $this->eventStore);

        // Reset static tracking
        OrderActivity::$executedActions = [];
    }

    // =========================================================================
    // 1. Register workflow via attribute
    // =========================================================================

    public function test_register_workflow_via_attribute(): void
    {
        $this->registry->registerWorkflow(GreetingWorkflow::class);

        $this->assertTrue($this->registry->hasWorkflow('GreetingWorkflow'));
        $this->assertSame(GreetingWorkflow::class, $this->registry->getWorkflowClass('GreetingWorkflow'));
    }

    public function test_register_workflow_without_attribute_throws(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('not annotated with #[Workflow]');

        $this->registry->registerWorkflow(\stdClass::class);
    }

    // =========================================================================
    // 2. Register activity via attribute
    // =========================================================================

    public function test_register_activity_via_attribute(): void
    {
        $this->registry->registerActivity(GreetingActivity::class);

        $this->assertTrue($this->registry->hasActivity('GreetingActivity'));
        $this->assertSame(GreetingActivity::class, $this->registry->getActivityClass('GreetingActivity'));
    }

    public function test_register_activity_without_attribute_throws(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('not annotated with #[Activity]');

        $this->registry->registerActivity(\stdClass::class);
    }

    // =========================================================================
    // 3. Start workflow -- execution created in event store
    // =========================================================================

    public function test_start_workflow_creates_execution(): void
    {
        $this->registry->registerWorkflow(GreetingWorkflow::class);

        $handle = $this->client->start(
            GreetingWorkflow::class,
            'World',
        );

        $this->assertNotEmpty($handle->getWorkflowId());
        $this->assertNotEmpty($handle->getRunId());

        // Execution should exist in event store
        $execution = $this->eventStore->findExecutionByWorkflowId($handle->getWorkflowId());
        $this->assertNotNull($execution);
    }

    public function test_start_workflow_with_explicit_workflow_id(): void
    {
        $this->registry->registerWorkflow(GreetingWorkflow::class);

        $options = new WorkflowOptions(workflowId: 'my-custom-id');
        $handle = $this->client->start(
            GreetingWorkflow::class,
            'World',
            $options,
        );

        $this->assertSame('my-custom-id', $handle->getWorkflowId());
    }

    // =========================================================================
    // 4. Workflow executes activities
    // =========================================================================

    public function test_workflow_executes_activities(): void
    {
        $this->registry->registerWorkflow(MultiStepWorkflow::class);

        $handle = $this->client->start(
            MultiStepWorkflow::class,
            ['value' => 'input_data'],
        );

        $result = $handle->getResult();

        $this->assertIsArray($result);
        $this->assertSame('first:input_data', $result['step1']);
        $this->assertSame('second:first:input_data', $result['step2']);
    }

    // =========================================================================
    // 5. Workflow completes -- status = Completed, result stored
    // =========================================================================

    public function test_workflow_completes_with_result(): void
    {
        $this->registry->registerWorkflow(GreetingWorkflow::class);

        $handle = $this->client->start(
            GreetingWorkflow::class,
            'LatticePHP',
        );

        $this->assertSame(WorkflowStatus::Completed, $handle->getStatus());
        $this->assertSame('Hello, LatticePHP!', $handle->getResult());
    }

    public function test_completed_workflow_has_events_in_store(): void
    {
        $this->registry->registerWorkflow(GreetingWorkflow::class);

        $options = new WorkflowOptions(workflowId: 'events-test');
        $handle = $this->client->start(
            GreetingWorkflow::class,
            'Test',
            $options,
        );

        $execution = $this->eventStore->findExecutionByWorkflowId('events-test');
        $events = $this->eventStore->getEvents($execution->getId());

        // Should have at minimum: WorkflowStarted, ActivityScheduled, ActivityStarted, ActivityCompleted, WorkflowCompleted
        $this->assertGreaterThanOrEqual(5, count($events));
    }

    // =========================================================================
    // 6. Signal workflow
    // =========================================================================

    public function test_signal_workflow_executes_handler(): void
    {
        $this->registry->registerWorkflow(SignalQueryWorkflow::class);

        $options = new WorkflowOptions(workflowId: 'signal-test');
        $handle = $this->client->start(SignalQueryWorkflow::class, null, $options);

        // Workflow should have completed
        $this->assertSame(WorkflowStatus::Completed, $handle->getStatus());

        // Send a signal
        $handle->signal('increment');

        // Query state after signal -- the signal is delivered during the query replay
        $status = $handle->query('getStatus');
        // After signal delivery during query, the status should reflect the signal handler
        $this->assertSame('incremented_1', $status);

        $counter = $handle->query('getCounter');
        $this->assertSame(1, $counter);
    }

    // =========================================================================
    // 7. Query workflow
    // =========================================================================

    public function test_query_workflow_returns_current_state(): void
    {
        $this->registry->registerWorkflow(SignalQueryWorkflow::class);

        $options = new WorkflowOptions(workflowId: 'query-test');
        $handle = $this->client->start(SignalQueryWorkflow::class, null, $options);

        // Query the status -- workflow has completed at this point
        $status = $handle->query('getStatus');
        // After execution completes, status is 'activity_done'
        $this->assertSame('activity_done', $status);
    }

    public function test_query_nonexistent_method_throws(): void
    {
        $this->registry->registerWorkflow(SignalQueryWorkflow::class);

        $options = new WorkflowOptions(workflowId: 'query-fail-test');
        $handle = $this->client->start(SignalQueryWorkflow::class, null, $options);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Query method not found');
        $handle->query('nonExistentQuery');
    }

    // =========================================================================
    // 8. Compensation / saga -- compensations run in reverse order
    // =========================================================================

    public function test_compensation_saga_runs_compensations_in_reverse(): void
    {
        $this->registry->registerWorkflow(SagaCompensationWorkflow::class);

        // Trigger the compensation path
        $handle = $this->client->start(
            SagaCompensationWorkflow::class,
            ['amount' => 100.0, 'item' => 'widget', 'fail' => true],
        );

        // The workflow fails because compensate() throws when there are no failures
        // Actually CompensationScope::compensate() only throws if compensations themselves fail.
        // Check executed actions: charge, reserve, then release (reverse first), then refund (reverse second)
        $actions = OrderActivity::$executedActions;

        // Forward actions happen first
        $this->assertStringContainsString('charge:', $actions[0]);
        $this->assertStringContainsString('reserve:', $actions[1]);

        // Compensations happen in reverse: release first, then refund
        $this->assertStringContainsString('release:', $actions[2]);
        $this->assertStringContainsString('refund:', $actions[3]);
    }

    public function test_compensation_saga_happy_path_no_compensation(): void
    {
        $this->registry->registerWorkflow(SagaCompensationWorkflow::class);

        $handle = $this->client->start(
            SagaCompensationWorkflow::class,
            ['amount' => 50.0, 'item' => 'gadget', 'fail' => false],
        );

        $this->assertSame(WorkflowStatus::Completed, $handle->getStatus());

        $result = $handle->getResult();
        $this->assertArrayHasKey('charge', $result);
        $this->assertArrayHasKey('reserve', $result);

        // Only forward actions, no compensations
        $actions = OrderActivity::$executedActions;
        $this->assertCount(2, $actions);
        $this->assertStringContainsString('charge:', $actions[0]);
        $this->assertStringContainsString('reserve:', $actions[1]);
    }

    // =========================================================================
    // 9. WorkflowTestEnvironment -- stubs for activities, verify workflow logic
    // =========================================================================

    public function test_workflow_test_environment(): void
    {
        $env = new WorkflowTestEnvironment();
        $env->registerWorkflow(GreetingWorkflow::class);
        $env->registerActivity(GreetingActivity::class);

        $handle = $env->startWorkflow(GreetingWorkflow::class, 'TestEnv');

        $env->assertWorkflowStarted(GreetingWorkflow::class);
        $this->assertSame('Hello, TestEnv!', $handle->getResult());
    }

    public function test_workflow_test_environment_with_stubbed_activity(): void
    {
        $env = new WorkflowTestEnvironment();
        $env->registerWorkflow(GreetingWorkflow::class);

        // Register a mock/stub activity instance
        $stubActivity = new class {
            public function greet(string $name): string
            {
                return "Stubbed greeting for {$name}";
            }
        };
        $env->registerActivityInstance(GreetingActivity::class, $stubActivity);

        $handle = $env->startWorkflow(GreetingWorkflow::class, 'Stub');

        $this->assertSame('Stubbed greeting for Stub', $handle->getResult());
    }

    public function test_workflow_test_environment_signal(): void
    {
        $env = new WorkflowTestEnvironment();
        $env->registerWorkflow(SignalQueryWorkflow::class);

        $options = new WorkflowOptions(workflowId: 'env-signal-test');
        $handle = $env->startWorkflow(SignalQueryWorkflow::class, null, $options);

        $this->assertSame(WorkflowStatus::Completed, $handle->getStatus());

        $env->signalWorkflow('env-signal-test', 'increment');
        $env->assertSignalSent('env-signal-test', 'increment');

        // Query the state after signal
        $status = $handle->query('getStatus');
        $this->assertSame('incremented_1', $status);
    }

    // =========================================================================
    // 10. FULL CYCLE: start -> activities -> signal -> query -> complete -> verify
    // =========================================================================

    public function test_full_cycle_workflow(): void
    {
        $this->registry->registerWorkflow(FullCycleWorkflow::class);

        $options = new WorkflowOptions(workflowId: 'full-cycle-test');
        $handle = $this->client->start(
            FullCycleWorkflow::class,
            ['data' => 'full_test'],
            $options,
        );

        // --- Step 1: Verify workflow completed ---
        $this->assertSame(WorkflowStatus::Completed, $handle->getStatus());

        // --- Step 2: Verify result includes activity output ---
        $result = $handle->getResult();
        $this->assertIsArray($result);
        $this->assertSame('process:full_test', $result['result']);
        $this->assertContains('started', $result['log']);
        $this->assertContains('activity_completed', $result['log']);

        // --- Step 3: Send a signal ---
        $handle->signal('addNote', 'test-note');

        // --- Step 4: Query state after signal ---
        $state = $handle->query('getState');
        $this->assertSame('noted', $state);

        $log = $handle->query('getLog');
        $this->assertContains('note:test-note', $log);

        // --- Step 5: Verify events were persisted ---
        $execution = $this->eventStore->findExecutionByWorkflowId('full-cycle-test');
        $events = $this->eventStore->getEvents($execution->getId());
        $this->assertNotEmpty($events);

        // Should have WorkflowStarted as first event type
        $firstEvent = $events[0];
        $this->assertSame(
            \Lattice\Contracts\Workflow\WorkflowEventType::WorkflowStarted,
            $firstEvent->getEventType(),
        );
    }

    // =========================================================================
    // Additional: workflow cancel/terminate
    // =========================================================================

    public function test_cancel_workflow(): void
    {
        $this->registry->registerWorkflow(GreetingWorkflow::class);

        $options = new WorkflowOptions(workflowId: 'cancel-test');
        $handle = $this->client->start(GreetingWorkflow::class, 'CancelMe', $options);

        // Workflow already completed (sync), cancel changes status
        $handle->cancel();
        $this->assertSame(WorkflowStatus::Cancelled, $handle->getStatus());
    }

    public function test_terminate_workflow(): void
    {
        $this->registry->registerWorkflow(GreetingWorkflow::class);

        $options = new WorkflowOptions(workflowId: 'terminate-test');
        $handle = $this->client->start(GreetingWorkflow::class, 'TerminateMe', $options);

        $handle->terminate('test termination');
        $this->assertSame(WorkflowStatus::Terminated, $handle->getStatus());
    }

    // =========================================================================
    // Additional: get handle for existing workflow
    // =========================================================================

    public function test_get_handle_for_existing_workflow(): void
    {
        $this->registry->registerWorkflow(GreetingWorkflow::class);

        $options = new WorkflowOptions(workflowId: 'existing-wf');
        $this->client->start(GreetingWorkflow::class, 'Existing', $options);

        $handle = $this->client->getHandle('existing-wf');
        $this->assertSame('existing-wf', $handle->getWorkflowId());
        $this->assertSame(WorkflowStatus::Completed, $handle->getStatus());
        $this->assertSame('Hello, Existing!', $handle->getResult());
    }
}
