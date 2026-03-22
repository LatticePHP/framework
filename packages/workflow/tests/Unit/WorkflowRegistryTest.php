<?php

declare(strict_types=1);

namespace Lattice\Workflow\Tests\Unit;

use Lattice\Workflow\Registry\WorkflowRegistry;
use Lattice\Workflow\Tests\Fixtures\OrderFulfillmentWorkflow;
use Lattice\Workflow\Tests\Fixtures\PaymentActivity;
use Lattice\Workflow\Tests\Fixtures\ShippingActivity;
use Lattice\Workflow\Tests\Fixtures\SimpleWorkflow;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class WorkflowRegistryTest extends TestCase
{
    private WorkflowRegistry $registry;

    protected function setUp(): void
    {
        $this->registry = new WorkflowRegistry();
    }

    #[Test]
    public function it_registers_and_looks_up_a_workflow(): void
    {
        $this->registry->registerWorkflow(OrderFulfillmentWorkflow::class);

        $class = $this->registry->getWorkflowClass('OrderFulfillmentWorkflow');
        $this->assertSame(OrderFulfillmentWorkflow::class, $class);
    }

    #[Test]
    public function it_registers_workflow_with_custom_name(): void
    {
        $this->registry->registerWorkflow(SimpleWorkflow::class);

        $class = $this->registry->getWorkflowClass('SimpleWorkflow');
        $this->assertSame(SimpleWorkflow::class, $class);
    }

    #[Test]
    public function it_registers_and_looks_up_an_activity(): void
    {
        $this->registry->registerActivity(PaymentActivity::class);

        $class = $this->registry->getActivityClass('PaymentActivity');
        $this->assertSame(PaymentActivity::class, $class);
    }

    #[Test]
    public function it_throws_for_unregistered_workflow(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('not registered');

        $this->registry->getWorkflowClass('NonExistent');
    }

    #[Test]
    public function it_throws_for_unregistered_activity(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('not registered');

        $this->registry->getActivityClass('NonExistent');
    }

    #[Test]
    public function it_throws_when_registering_class_without_workflow_attribute(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('not annotated');

        $this->registry->registerWorkflow(\stdClass::class);
    }

    #[Test]
    public function it_throws_when_registering_class_without_activity_attribute(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('not annotated');

        $this->registry->registerActivity(\stdClass::class);
    }

    #[Test]
    public function it_checks_if_workflow_is_registered(): void
    {
        $this->assertFalse($this->registry->hasWorkflow('OrderFulfillmentWorkflow'));

        $this->registry->registerWorkflow(OrderFulfillmentWorkflow::class);

        $this->assertTrue($this->registry->hasWorkflow('OrderFulfillmentWorkflow'));
    }

    #[Test]
    public function it_checks_if_activity_is_registered(): void
    {
        $this->assertFalse($this->registry->hasActivity('PaymentActivity'));

        $this->registry->registerActivity(PaymentActivity::class);

        $this->assertTrue($this->registry->hasActivity('PaymentActivity'));
    }

    #[Test]
    public function it_lists_registered_workflows(): void
    {
        $this->registry->registerWorkflow(OrderFulfillmentWorkflow::class);
        $this->registry->registerWorkflow(SimpleWorkflow::class);

        $workflows = $this->registry->getRegisteredWorkflows();
        $this->assertCount(2, $workflows);
        $this->assertArrayHasKey('OrderFulfillmentWorkflow', $workflows);
        $this->assertArrayHasKey('SimpleWorkflow', $workflows);
    }

    #[Test]
    public function it_lists_registered_activities(): void
    {
        $this->registry->registerActivity(PaymentActivity::class);
        $this->registry->registerActivity(ShippingActivity::class);

        $activities = $this->registry->getRegisteredActivities();
        $this->assertCount(2, $activities);
        $this->assertArrayHasKey('PaymentActivity', $activities);
        $this->assertArrayHasKey('ShippingActivity', $activities);
    }
}
