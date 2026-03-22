<?php

declare(strict_types=1);

namespace Lattice\Workflow\Tests\Unit;

use Lattice\Contracts\Workflow\WorkflowStatus;
use Lattice\Workflow\Testing\ActivityStub;
use Lattice\Workflow\Testing\WorkflowTestEnvironment;
use Lattice\Workflow\Tests\Fixtures\OrderFulfillmentWorkflow;
use Lattice\Workflow\Tests\Fixtures\PaymentActivity;
use Lattice\Workflow\Tests\Fixtures\ShippingActivity;
use Lattice\Workflow\Tests\Fixtures\SimpleWorkflow;
use Lattice\Workflow\WorkflowOptions;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class WorkflowTestEnvironmentTest extends TestCase
{
    #[Test]
    public function it_runs_a_simple_workflow_end_to_end(): void
    {
        $env = new WorkflowTestEnvironment();

        $handle = $env->startWorkflow(SimpleWorkflow::class);

        $this->assertSame(WorkflowStatus::Completed, $handle->getStatus());
        $this->assertSame('simple_result', $handle->getResult());
    }

    #[Test]
    public function it_runs_workflow_with_real_activities(): void
    {
        $env = new WorkflowTestEnvironment();

        $handle = $env->startWorkflow(
            OrderFulfillmentWorkflow::class,
            ['amount' => 99.0, 'address' => '123 Test St'],
        );

        $this->assertSame(WorkflowStatus::Completed, $handle->getStatus());

        $result = $handle->getResult();
        $this->assertArrayHasKey('payment', $result);
        $this->assertArrayHasKey('shipping', $result);
    }

    #[Test]
    public function it_uses_activity_stubs_for_mocking(): void
    {
        $env = new WorkflowTestEnvironment();

        $paymentStub = new ActivityStub();
        $paymentStub->willReturn('charge', 'mocked_payment_123');

        $shippingStub = new ActivityStub();
        $shippingStub->willReturn('ship', 'mocked_tracking_456');

        $env->registerActivityInstance(PaymentActivity::class, $paymentStub);
        $env->registerActivityInstance(ShippingActivity::class, $shippingStub);

        $handle = $env->startWorkflow(
            OrderFulfillmentWorkflow::class,
            ['amount' => 50.0, 'address' => 'Mock St'],
        );

        $result = $handle->getResult();
        $this->assertSame('mocked_payment_123', $result['payment']);
        $this->assertSame('mocked_tracking_456', $result['shipping']);

        // Verify stubs were called
        $paymentStub->assertCalled('charge');
        $shippingStub->assertCalled('ship');
    }

    #[Test]
    public function it_tracks_started_workflows(): void
    {
        $env = new WorkflowTestEnvironment();

        $env->startWorkflow(SimpleWorkflow::class);

        // Should not throw
        $env->assertWorkflowStarted(SimpleWorkflow::class);
        $this->assertTrue(true); // Assertion happened inside assertWorkflowStarted
    }

    #[Test]
    public function it_throws_when_asserting_unstarted_workflow(): void
    {
        $env = new WorkflowTestEnvironment();

        $this->expectException(\RuntimeException::class);
        $env->assertWorkflowStarted('NonExistentWorkflow');
    }

    #[Test]
    public function it_sends_signals_and_tracks_them(): void
    {
        $env = new WorkflowTestEnvironment();
        $options = new WorkflowOptions(workflowId: 'signal_env_test');

        $env->startWorkflow(
            OrderFulfillmentWorkflow::class,
            ['amount' => 10.0, 'address' => 'Test'],
            $options,
        );

        $env->signalWorkflow('signal_env_test', 'markDelivered');

        // Should not throw
        $env->assertSignalSent('signal_env_test', 'markDelivered');
        $this->assertTrue(true); // Assertion happened inside assertSignalSent
    }

    #[Test]
    public function it_registers_workflows_in_registry(): void
    {
        $env = new WorkflowTestEnvironment();
        $env->registerWorkflow(SimpleWorkflow::class);

        $this->assertTrue($env->getRegistry()->hasWorkflow('SimpleWorkflow'));
    }

    #[Test]
    public function it_queries_workflow_through_runtime(): void
    {
        $env = new WorkflowTestEnvironment();
        $options = new WorkflowOptions(workflowId: 'query_env_test');

        $env->startWorkflow(
            OrderFulfillmentWorkflow::class,
            ['amount' => 10.0, 'address' => 'Test'],
            $options,
        );

        $status = $env->getRuntime()->queryWorkflow('query_env_test', 'getStatus');
        $this->assertSame('shipped', $status);
    }
}
