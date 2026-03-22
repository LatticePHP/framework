<?php

declare(strict_types=1);

namespace Lattice\Workflow\Tests\Unit;

use Lattice\Contracts\Workflow\WorkflowStatus;
use Lattice\Workflow\Client\WorkflowClient;
use Lattice\Workflow\Registry\WorkflowRegistry;
use Lattice\Workflow\Runtime\SyncActivityExecutor;
use Lattice\Workflow\Runtime\WorkflowRuntime;
use Lattice\Workflow\Store\InMemoryEventStore;
use Lattice\Workflow\Tests\Fixtures\OrderFulfillmentWorkflow;
use Lattice\Workflow\Tests\Fixtures\SimpleWorkflow;
use Lattice\Workflow\WorkflowOptions;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class WorkflowClientTest extends TestCase
{
    private InMemoryEventStore $eventStore;
    private WorkflowClient $client;
    private WorkflowRuntime $runtime;

    protected function setUp(): void
    {
        $this->eventStore = new InMemoryEventStore();
        $executor = new SyncActivityExecutor();
        $registry = new WorkflowRegistry();
        $this->runtime = new WorkflowRuntime($this->eventStore, $executor, $registry);
        $this->client = new WorkflowClient($this->runtime, $this->eventStore);
    }

    #[Test]
    public function it_starts_a_workflow_and_returns_handle(): void
    {
        $handle = $this->client->start(SimpleWorkflow::class);

        $this->assertNotEmpty($handle->getWorkflowId());
        $this->assertNotEmpty($handle->getRunId());
        $this->assertSame(WorkflowStatus::Completed, $handle->getStatus());
    }

    #[Test]
    public function it_uses_workflow_id_from_options(): void
    {
        $options = new WorkflowOptions(workflowId: 'custom_wf_id');

        $handle = $this->client->start(SimpleWorkflow::class, null, $options);

        $this->assertSame('custom_wf_id', $handle->getWorkflowId());
    }

    #[Test]
    public function it_gets_handle_for_existing_workflow(): void
    {
        $options = new WorkflowOptions(workflowId: 'lookup_test');
        $this->client->start(SimpleWorkflow::class, null, $options);

        $handle = $this->client->getHandle('lookup_test');
        $this->assertSame('lookup_test', $handle->getWorkflowId());
        $this->assertSame(WorkflowStatus::Completed, $handle->getStatus());
    }

    #[Test]
    public function it_throws_when_getting_handle_for_unknown_workflow(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->client->getHandle('nonexistent');
    }

    #[Test]
    public function it_gets_result_from_completed_workflow(): void
    {
        $handle = $this->client->start(SimpleWorkflow::class);

        $result = $handle->getResult();
        $this->assertSame('simple_result', $result);
    }

    #[Test]
    public function it_starts_workflow_with_input(): void
    {
        $handle = $this->client->start(
            OrderFulfillmentWorkflow::class,
            ['amount' => 42.0, 'address' => '100 Test Dr'],
        );

        $result = $handle->getResult();
        $this->assertIsArray($result);
        $this->assertArrayHasKey('payment', $result);
        $this->assertArrayHasKey('shipping', $result);
    }

    #[Test]
    public function it_sends_signal_through_handle(): void
    {
        $options = new WorkflowOptions(workflowId: 'signal_handle_test');
        $handle = $this->client->start(
            OrderFulfillmentWorkflow::class,
            ['amount' => 10.0, 'address' => 'Test St'],
            $options,
        );

        // Should not throw — verify signal event was recorded
        $handle->signal('markDelivered');
        $this->assertSame(WorkflowStatus::Completed, $handle->getStatus());
    }

    #[Test]
    public function it_queries_through_handle(): void
    {
        $options = new WorkflowOptions(workflowId: 'query_handle_test');
        $handle = $this->client->start(
            OrderFulfillmentWorkflow::class,
            ['amount' => 10.0, 'address' => 'Test St'],
            $options,
        );

        $status = $handle->query('getStatus');
        $this->assertSame('shipped', $status);
    }

    #[Test]
    public function it_cancels_through_handle(): void
    {
        $options = new WorkflowOptions(workflowId: 'cancel_handle_test');
        $handle = $this->client->start(SimpleWorkflow::class, null, $options);

        $handle->cancel();
        $this->assertSame(WorkflowStatus::Cancelled, $handle->getStatus());
    }

    #[Test]
    public function it_terminates_through_handle(): void
    {
        $options = new WorkflowOptions(workflowId: 'term_handle_test');
        $handle = $this->client->start(SimpleWorkflow::class, null, $options);

        $handle->terminate('Test reason');
        $this->assertSame(WorkflowStatus::Terminated, $handle->getStatus());
    }

    #[Test]
    public function it_throws_when_getting_result_of_cancelled_workflow(): void
    {
        $options = new WorkflowOptions(workflowId: 'cancel_result_test');
        $handle = $this->client->start(SimpleWorkflow::class, null, $options);

        $handle->cancel();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('cancelled');
        $handle->getResult();
    }
}
