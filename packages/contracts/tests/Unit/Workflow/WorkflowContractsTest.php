<?php

declare(strict_types=1);

namespace Lattice\Contracts\Tests\Unit\Workflow;

use Lattice\Contracts\Workflow\ActivityContextInterface;
use Lattice\Contracts\Workflow\RetryPolicyInterface;
use Lattice\Contracts\Workflow\WorkflowClientInterface;
use Lattice\Contracts\Workflow\WorkflowEventInterface;
use Lattice\Contracts\Workflow\WorkflowEventStoreInterface;
use Lattice\Contracts\Workflow\WorkflowEventType;
use Lattice\Contracts\Workflow\WorkflowExecutionInterface;
use Lattice\Contracts\Workflow\WorkflowHandleInterface;
use Lattice\Contracts\Workflow\WorkflowOptionsInterface;
use Lattice\Contracts\Workflow\WorkflowStatus;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionEnum;

final class WorkflowContractsTest extends TestCase
{
    // --- WorkflowStatus enum ---

    #[Test]
    public function workflowStatusEnumExists(): void
    {
        $this->assertTrue(enum_exists(WorkflowStatus::class));
    }

    #[Test]
    public function workflowStatusEnumIsStringBacked(): void
    {
        $reflection = new ReflectionEnum(WorkflowStatus::class);
        $this->assertTrue($reflection->isBacked());
        $this->assertSame('string', $reflection->getBackingType()->getName());
    }

    #[Test]
    public function workflowStatusEnumHasExpectedCases(): void
    {
        $expectedCases = [
            'Running' => 'running',
            'Completed' => 'completed',
            'Failed' => 'failed',
            'Cancelled' => 'cancelled',
            'Terminated' => 'terminated',
            'TimedOut' => 'timed_out',
        ];

        $cases = WorkflowStatus::cases();
        $this->assertCount(count($expectedCases), $cases);

        foreach ($expectedCases as $name => $value) {
            $case = WorkflowStatus::from($value);
            $this->assertSame($name, $case->name);
            $this->assertSame($value, $case->value);
        }
    }

    // --- WorkflowEventType enum ---

    #[Test]
    public function workflowEventTypeEnumExists(): void
    {
        $this->assertTrue(enum_exists(WorkflowEventType::class));
    }

    #[Test]
    public function workflowEventTypeEnumIsStringBacked(): void
    {
        $reflection = new ReflectionEnum(WorkflowEventType::class);
        $this->assertTrue($reflection->isBacked());
        $this->assertSame('string', $reflection->getBackingType()->getName());
    }

    #[Test]
    public function workflowEventTypeEnumHasExpectedCases(): void
    {
        $expectedCases = [
            'WorkflowStarted' => 'workflow_started',
            'WorkflowCompleted' => 'workflow_completed',
            'WorkflowFailed' => 'workflow_failed',
            'WorkflowCancelled' => 'workflow_cancelled',
            'WorkflowTerminated' => 'workflow_terminated',
            'ActivityScheduled' => 'activity_scheduled',
            'ActivityStarted' => 'activity_started',
            'ActivityCompleted' => 'activity_completed',
            'ActivityFailed' => 'activity_failed',
            'ActivityTimedOut' => 'activity_timed_out',
            'TimerStarted' => 'timer_started',
            'TimerFired' => 'timer_fired',
            'TimerCancelled' => 'timer_cancelled',
            'SignalReceived' => 'signal_received',
            'QueryReceived' => 'query_received',
            'UpdateReceived' => 'update_received',
            'ChildWorkflowStarted' => 'child_workflow_started',
            'ChildWorkflowCompleted' => 'child_workflow_completed',
            'ChildWorkflowFailed' => 'child_workflow_failed',
        ];

        $cases = WorkflowEventType::cases();
        $this->assertCount(count($expectedCases), $cases);

        foreach ($expectedCases as $name => $value) {
            $case = WorkflowEventType::from($value);
            $this->assertSame($name, $case->name);
            $this->assertSame($value, $case->value);
        }
    }

    // --- WorkflowClientInterface ---

    #[Test]
    public function workflowClientInterfaceExists(): void
    {
        $this->assertTrue(interface_exists(WorkflowClientInterface::class));
    }

    #[Test]
    public function workflowClientInterfaceHasExpectedMethods(): void
    {
        $reflection = new ReflectionClass(WorkflowClientInterface::class);

        $this->assertTrue($reflection->hasMethod('start'));
        $this->assertTrue($reflection->hasMethod('getHandle'));

        $this->assertSame(
            WorkflowHandleInterface::class,
            $reflection->getMethod('start')->getReturnType()->getName()
        );
        $this->assertSame(
            WorkflowHandleInterface::class,
            $reflection->getMethod('getHandle')->getReturnType()->getName()
        );
    }

    // --- WorkflowHandleInterface ---

    #[Test]
    public function workflowHandleInterfaceExists(): void
    {
        $this->assertTrue(interface_exists(WorkflowHandleInterface::class));
    }

    #[Test]
    public function workflowHandleInterfaceHasExpectedMethods(): void
    {
        $reflection = new ReflectionClass(WorkflowHandleInterface::class);

        $expectedMethods = [
            'getWorkflowId' => 'string',
            'getRunId' => 'string',
            'signal' => 'void',
            'query' => 'mixed',
            'update' => 'mixed',
            'cancel' => 'void',
            'terminate' => 'void',
            'getResult' => 'mixed',
            'getStatus' => WorkflowStatus::class,
        ];

        foreach ($expectedMethods as $methodName => $returnType) {
            $this->assertTrue($reflection->hasMethod($methodName), "Missing method: $methodName");
            $this->assertSame(
                $returnType,
                $reflection->getMethod($methodName)->getReturnType()->getName(),
                "Wrong return type for $methodName"
            );
        }
    }

    // --- WorkflowOptionsInterface ---

    #[Test]
    public function workflowOptionsInterfaceExists(): void
    {
        $this->assertTrue(interface_exists(WorkflowOptionsInterface::class));
    }

    #[Test]
    public function workflowOptionsInterfaceHasExpectedMethods(): void
    {
        $reflection = new ReflectionClass(WorkflowOptionsInterface::class);

        $this->assertTrue($reflection->hasMethod('getWorkflowId'));
        $this->assertTrue($reflection->hasMethod('getTaskQueue'));
        $this->assertTrue($reflection->hasMethod('getExecutionTimeout'));
        $this->assertTrue($reflection->hasMethod('getRunTimeout'));
        $this->assertTrue($reflection->hasMethod('getRetryPolicy'));

        // getWorkflowId is nullable string
        $wfIdReturn = $reflection->getMethod('getWorkflowId')->getReturnType();
        $this->assertTrue($wfIdReturn->allowsNull());

        $this->assertSame('string', $reflection->getMethod('getTaskQueue')->getReturnType()->getName());

        // getRetryPolicy is nullable
        $retryReturn = $reflection->getMethod('getRetryPolicy')->getReturnType();
        $this->assertTrue($retryReturn->allowsNull());
    }

    // --- RetryPolicyInterface ---

    #[Test]
    public function retryPolicyInterfaceExists(): void
    {
        $this->assertTrue(interface_exists(RetryPolicyInterface::class));
    }

    #[Test]
    public function retryPolicyInterfaceHasExpectedMethods(): void
    {
        $reflection = new ReflectionClass(RetryPolicyInterface::class);

        $expectedMethods = [
            'getMaxAttempts' => 'int',
            'getInitialInterval' => 'int',
            'getBackoffCoefficient' => 'float',
            'getNonRetryableExceptions' => 'array',
        ];

        foreach ($expectedMethods as $methodName => $returnType) {
            $this->assertTrue($reflection->hasMethod($methodName), "Missing method: $methodName");
            $this->assertSame(
                $returnType,
                $reflection->getMethod($methodName)->getReturnType()->getName(),
                "Wrong return type for $methodName"
            );
        }

        // getMaxInterval is nullable int
        $maxIntervalReturn = $reflection->getMethod('getMaxInterval')->getReturnType();
        $this->assertTrue($maxIntervalReturn->allowsNull());
    }

    // --- ActivityContextInterface ---

    #[Test]
    public function activityContextInterfaceExists(): void
    {
        $this->assertTrue(interface_exists(ActivityContextInterface::class));
    }

    #[Test]
    public function activityContextInterfaceHasExpectedMethods(): void
    {
        $reflection = new ReflectionClass(ActivityContextInterface::class);

        $expectedMethods = [
            'getWorkflowId' => 'string',
            'getActivityId' => 'string',
            'getAttempt' => 'int',
            'heartbeat' => 'void',
        ];

        foreach ($expectedMethods as $methodName => $returnType) {
            $this->assertTrue($reflection->hasMethod($methodName), "Missing method: $methodName");
            $this->assertSame(
                $returnType,
                $reflection->getMethod($methodName)->getReturnType()->getName(),
                "Wrong return type for $methodName"
            );
        }
    }

    // --- WorkflowEventInterface ---

    #[Test]
    public function workflowEventInterfaceExists(): void
    {
        $this->assertTrue(interface_exists(WorkflowEventInterface::class));
    }

    #[Test]
    public function workflowEventInterfaceHasExpectedMethods(): void
    {
        $reflection = new ReflectionClass(WorkflowEventInterface::class);

        $this->assertTrue($reflection->hasMethod('getEventType'));
        $this->assertTrue($reflection->hasMethod('getSequenceNumber'));
        $this->assertTrue($reflection->hasMethod('getPayload'));
        $this->assertTrue($reflection->hasMethod('getTimestamp'));

        $this->assertSame(
            WorkflowEventType::class,
            $reflection->getMethod('getEventType')->getReturnType()->getName()
        );
        $this->assertSame('int', $reflection->getMethod('getSequenceNumber')->getReturnType()->getName());
        $this->assertSame('mixed', $reflection->getMethod('getPayload')->getReturnType()->getName());
        $this->assertSame(
            \DateTimeImmutable::class,
            $reflection->getMethod('getTimestamp')->getReturnType()->getName()
        );
    }

    // --- WorkflowEventStoreInterface ---

    #[Test]
    public function workflowEventStoreInterfaceExists(): void
    {
        $this->assertTrue(interface_exists(WorkflowEventStoreInterface::class));
    }

    #[Test]
    public function workflowEventStoreInterfaceHasExpectedMethods(): void
    {
        $reflection = new ReflectionClass(WorkflowEventStoreInterface::class);

        $this->assertTrue($reflection->hasMethod('appendEvent'));
        $this->assertTrue($reflection->hasMethod('getEvents'));
        $this->assertTrue($reflection->hasMethod('createExecution'));
        $this->assertTrue($reflection->hasMethod('updateExecutionStatus'));
        $this->assertTrue($reflection->hasMethod('getExecution'));
        $this->assertTrue($reflection->hasMethod('findExecutionByWorkflowId'));

        $this->assertSame('void', $reflection->getMethod('appendEvent')->getReturnType()->getName());
        $this->assertSame('array', $reflection->getMethod('getEvents')->getReturnType()->getName());
        $this->assertSame('string', $reflection->getMethod('createExecution')->getReturnType()->getName());
        $this->assertSame('void', $reflection->getMethod('updateExecutionStatus')->getReturnType()->getName());

        // nullable returns
        $getExecReturn = $reflection->getMethod('getExecution')->getReturnType();
        $this->assertTrue($getExecReturn->allowsNull());

        $findExecReturn = $reflection->getMethod('findExecutionByWorkflowId')->getReturnType();
        $this->assertTrue($findExecReturn->allowsNull());
    }

    // --- WorkflowExecutionInterface ---

    #[Test]
    public function workflowExecutionInterfaceExists(): void
    {
        $this->assertTrue(interface_exists(WorkflowExecutionInterface::class));
    }

    #[Test]
    public function workflowExecutionInterfaceHasExpectedMethods(): void
    {
        $reflection = new ReflectionClass(WorkflowExecutionInterface::class);

        $expectedMethods = [
            'getId' => 'string',
            'getWorkflowType' => 'string',
            'getWorkflowId' => 'string',
            'getRunId' => 'string',
            'getInput' => 'mixed',
            'getStatus' => WorkflowStatus::class,
            'getResult' => 'mixed',
            'getStartedAt' => \DateTimeImmutable::class,
        ];

        foreach ($expectedMethods as $methodName => $returnType) {
            $this->assertTrue($reflection->hasMethod($methodName), "Missing method: $methodName");
            $this->assertSame(
                $returnType,
                $reflection->getMethod($methodName)->getReturnType()->getName(),
                "Wrong return type for $methodName"
            );
        }

        // nullable returns
        $completedAtReturn = $reflection->getMethod('getCompletedAt')->getReturnType();
        $this->assertTrue($completedAtReturn->allowsNull());

        $parentReturn = $reflection->getMethod('getParentWorkflowId')->getReturnType();
        $this->assertTrue($parentReturn->allowsNull());
    }
}
