<?php

declare(strict_types=1);

namespace Lattice\Workflow\Tests\Unit;

use Lattice\Contracts\Workflow\ActivityContextInterface;
use Lattice\Workflow\Runtime\ActivityContext;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ActivityContextTest extends TestCase
{
    #[Test]
    public function test_get_workflow_id_returns_constructor_value(): void
    {
        $context = new ActivityContext('wf-123', 'act-456', 1);

        $this->assertSame('wf-123', $context->getWorkflowId());
    }

    #[Test]
    public function test_get_activity_id_returns_constructor_value(): void
    {
        $context = new ActivityContext('wf-123', 'act-456', 1);

        $this->assertSame('act-456', $context->getActivityId());
    }

    #[Test]
    public function test_get_attempt_returns_constructor_value(): void
    {
        $context = new ActivityContext('wf-123', 'act-456', 3);

        $this->assertSame(3, $context->getAttempt());
    }

    #[Test]
    public function test_implements_activity_context_interface(): void
    {
        $context = new ActivityContext('wf-1', 'act-1', 1);

        $this->assertInstanceOf(ActivityContextInterface::class, $context);
    }

    #[Test]
    public function test_heartbeat_calls_callback_with_details(): void
    {
        $receivedDetails = null;
        $callback = function (mixed $details) use (&$receivedDetails): void {
            $receivedDetails = $details;
        };

        $context = new ActivityContext('wf-1', 'act-1', 1, $callback);
        $context->heartbeat(['progress' => 50]);

        $this->assertSame(['progress' => 50], $receivedDetails);
    }

    #[Test]
    public function test_heartbeat_calls_callback_with_null_details(): void
    {
        $called = false;
        $callback = function (mixed $details) use (&$called): void {
            $called = true;
        };

        $context = new ActivityContext('wf-1', 'act-1', 1, $callback);
        $context->heartbeat();

        $this->assertTrue($called);
    }

    #[Test]
    public function test_heartbeat_without_callback_does_not_throw(): void
    {
        $context = new ActivityContext('wf-1', 'act-1', 1);

        // Should not throw any exception
        $context->heartbeat('some details');

        $this->assertTrue(true); // If we reach here, no exception was thrown
    }

    #[Test]
    public function test_is_cancelled_defaults_to_false(): void
    {
        $context = new ActivityContext('wf-1', 'act-1', 1);

        $this->assertFalse($context->isCancelled());
    }

    #[Test]
    public function test_mark_cancelled_changes_is_cancelled_to_true(): void
    {
        $context = new ActivityContext('wf-1', 'act-1', 1);

        $context->markCancelled();

        $this->assertTrue($context->isCancelled());
    }

    #[Test]
    public function test_mark_cancelled_is_idempotent(): void
    {
        $context = new ActivityContext('wf-1', 'act-1', 1);

        $context->markCancelled();
        $context->markCancelled();

        $this->assertTrue($context->isCancelled());
    }
}
