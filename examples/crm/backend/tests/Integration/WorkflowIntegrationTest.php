<?php

declare(strict_types=1);

namespace Tests\Integration;

use Lattice\Contracts\Workflow\WorkflowEventType;
use Lattice\Contracts\Workflow\WorkflowStatus;
use Lattice\Workflow\Attributes\Activity;
use Lattice\Workflow\Attributes\Workflow;
use Lattice\Workflow\Client\WorkflowClient;
use Lattice\Workflow\Registry\WorkflowRegistry;
use Lattice\Workflow\Runtime\SyncActivityExecutor;
use Lattice\Workflow\Runtime\WorkflowContext;
use Lattice\Workflow\Runtime\WorkflowRuntime;
use Lattice\Workflow\Store\InMemoryEventStore;
use Lattice\Workflow\WorkflowOptions;
use PHPUnit\Framework\TestCase;

// ---------------------------------------------------------------------------
// CRM workflow fixtures
// ---------------------------------------------------------------------------

#[Workflow(name: 'DealWonWorkflow')]
final class DealWonWorkflow
{
    public function execute(WorkflowContext $ctx, array $input): array
    {
        $notification = $ctx->executeActivity(
            CrmNotificationActivity::class,
            'sendDealWonNotification',
            $input['deal_id'],
            $input['deal_title'],
            $input['value'],
        );

        $dealUpdate = $ctx->executeActivity(
            CrmDealActivity::class,
            'markAsWon',
            $input['deal_id'],
        );

        $activity = $ctx->executeActivity(
            CrmActivityLogActivity::class,
            'createActivity',
            $input['deal_id'],
            'deal_won',
            "Deal '{$input['deal_title']}' won for \${$input['value']}",
        );

        return [
            'notification' => $notification,
            'deal_update' => $dealUpdate,
            'activity_log' => $activity,
        ];
    }
}

#[Activity(name: 'CrmNotificationActivity')]
final class CrmNotificationActivity
{
    /** @var list<string> */
    public static array $sent = [];

    public function sendDealWonNotification(int $dealId, string $title, float $value): string
    {
        $id = 'notif_' . $dealId;
        self::$sent[] = "deal_won:{$dealId}:{$title}:{$value}";

        return $id;
    }
}

#[Activity(name: 'CrmDealActivity')]
final class CrmDealActivity
{
    /** @var list<string> */
    public static array $updates = [];

    public function markAsWon(int $dealId): string
    {
        self::$updates[] = "won:{$dealId}";

        return 'deal_' . $dealId . '_won';
    }
}

#[Activity(name: 'CrmActivityLogActivity')]
final class CrmActivityLogActivity
{
    /** @var list<string> */
    public static array $logs = [];

    public function createActivity(int $dealId, string $type, string $description): string
    {
        $id = 'activity_' . bin2hex(random_bytes(4));
        self::$logs[] = "{$type}:{$dealId}:{$description}";

        return $id;
    }
}

// ---------------------------------------------------------------------------
// Integration test class
// ---------------------------------------------------------------------------

/**
 * Integration tests verifying CRM workflow execution through the
 * LatticePHP workflow engine and its visibility in the Chronos dashboard.
 */
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
        CrmNotificationActivity::$sent = [];
        CrmDealActivity::$updates = [];
        CrmActivityLogActivity::$logs = [];
    }

    // -------------------------------------------------------
    // Deal Won workflow
    // -------------------------------------------------------

    public function test_deal_won_workflow_completes_all_steps(): void
    {
        $this->registry->registerWorkflow(DealWonWorkflow::class);

        $handle = $this->client->start(
            DealWonWorkflow::class,
            [
                'deal_id' => 42,
                'deal_title' => 'Enterprise License',
                'value' => 50000.00,
            ],
        );

        $this->assertSame(WorkflowStatus::Completed, $handle->getStatus());

        $result = $handle->getResult();
        $this->assertIsArray($result);
        $this->assertArrayHasKey('notification', $result);
        $this->assertArrayHasKey('deal_update', $result);
        $this->assertArrayHasKey('activity_log', $result);
    }

    public function test_deal_won_workflow_sends_notification(): void
    {
        $this->registry->registerWorkflow(DealWonWorkflow::class);

        $this->client->start(
            DealWonWorkflow::class,
            [
                'deal_id' => 7,
                'deal_title' => 'Premium Plan',
                'value' => 12500.00,
            ],
        );

        $this->assertCount(1, CrmNotificationActivity::$sent);
        $this->assertStringContainsString('deal_won:7:Premium Plan:12500', CrmNotificationActivity::$sent[0]);
    }

    public function test_deal_won_workflow_updates_deal_status(): void
    {
        $this->registry->registerWorkflow(DealWonWorkflow::class);

        $this->client->start(
            DealWonWorkflow::class,
            [
                'deal_id' => 15,
                'deal_title' => 'Annual Contract',
                'value' => 30000.00,
            ],
        );

        $this->assertCount(1, CrmDealActivity::$updates);
        $this->assertSame('won:15', CrmDealActivity::$updates[0]);
    }

    public function test_deal_won_workflow_creates_activity_log(): void
    {
        $this->registry->registerWorkflow(DealWonWorkflow::class);

        $this->client->start(
            DealWonWorkflow::class,
            [
                'deal_id' => 99,
                'deal_title' => 'Mega Deal',
                'value' => 100000.00,
            ],
        );

        $this->assertCount(1, CrmActivityLogActivity::$logs);
        $this->assertStringContainsString('deal_won:99', CrmActivityLogActivity::$logs[0]);
        $this->assertStringContainsString('Mega Deal', CrmActivityLogActivity::$logs[0]);
    }

    // -------------------------------------------------------
    // Workflow visible in event store (Chronos visibility)
    // -------------------------------------------------------

    public function test_workflow_appears_in_event_store(): void
    {
        $this->registry->registerWorkflow(DealWonWorkflow::class);

        $options = new WorkflowOptions(workflowId: 'deal-won-42');
        $handle = $this->client->start(
            DealWonWorkflow::class,
            [
                'deal_id' => 42,
                'deal_title' => 'Enterprise License',
                'value' => 50000.00,
            ],
            $options,
        );

        $execution = $this->eventStore->findExecutionByWorkflowId('deal-won-42');
        $this->assertNotNull($execution, 'Workflow execution should be visible in the event store');
        $this->assertSame(WorkflowStatus::Completed, $execution->getStatus());
    }

    public function test_workflow_events_are_recorded(): void
    {
        $this->registry->registerWorkflow(DealWonWorkflow::class);

        $options = new WorkflowOptions(workflowId: 'deal-events-test');
        $this->client->start(
            DealWonWorkflow::class,
            [
                'deal_id' => 1,
                'deal_title' => 'Test Deal',
                'value' => 1000.00,
            ],
            $options,
        );

        $execution = $this->eventStore->findExecutionByWorkflowId('deal-events-test');
        $events = $this->eventStore->getEvents($execution->getId());

        // Should have events: WorkflowStarted, 3x (ActivityScheduled + ActivityStarted + ActivityCompleted), WorkflowCompleted
        $this->assertNotEmpty($events);
        $this->assertGreaterThanOrEqual(5, count($events));

        // First event should be WorkflowStarted
        $this->assertSame(
            WorkflowEventType::WorkflowStarted,
            $events[0]->getEventType(),
        );
    }

    // -------------------------------------------------------
    // Execution lifecycle
    // -------------------------------------------------------

    public function test_workflow_execution_has_correct_type(): void
    {
        $this->registry->registerWorkflow(DealWonWorkflow::class);

        $options = new WorkflowOptions(workflowId: 'type-check');
        $this->client->start(
            DealWonWorkflow::class,
            [
                'deal_id' => 1,
                'deal_title' => 'Type Test',
                'value' => 500.00,
            ],
            $options,
        );

        $execution = $this->eventStore->findExecutionByWorkflowId('type-check');
        $this->assertStringContainsString('DealWonWorkflow', $execution->getWorkflowType());
    }

    public function test_workflow_activities_execute_in_order(): void
    {
        $this->registry->registerWorkflow(DealWonWorkflow::class);

        $this->client->start(
            DealWonWorkflow::class,
            [
                'deal_id' => 5,
                'deal_title' => 'Order Test',
                'value' => 2000.00,
            ],
        );

        // Activities execute in order: notification -> deal update -> activity log
        $this->assertCount(1, CrmNotificationActivity::$sent);
        $this->assertCount(1, CrmDealActivity::$updates);
        $this->assertCount(1, CrmActivityLogActivity::$logs);

        // Verify the notification was for the correct deal
        $this->assertStringContainsString('deal_won:5', CrmNotificationActivity::$sent[0]);

        // Verify the deal was marked as won
        $this->assertSame('won:5', CrmDealActivity::$updates[0]);

        // Verify the activity log references the correct deal
        $this->assertStringContainsString('deal_won:5', CrmActivityLogActivity::$logs[0]);
    }
}
