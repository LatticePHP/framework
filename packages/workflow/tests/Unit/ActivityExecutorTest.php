<?php

declare(strict_types=1);

namespace Lattice\Workflow\Tests\Unit;

use Lattice\Workflow\RetryPolicy;
use Lattice\Workflow\Runtime\SyncActivityExecutor;
use Lattice\Workflow\Tests\Fixtures\FailingActivity;
use Lattice\Workflow\Tests\Fixtures\PaymentActivity;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ActivityExecutorTest extends TestCase
{
    private SyncActivityExecutor $executor;

    protected function setUp(): void
    {
        $this->executor = new SyncActivityExecutor();
    }

    #[Test]
    public function it_executes_an_activity_successfully(): void
    {
        $result = $this->executor->execute(
            PaymentActivity::class,
            'charge',
            [100.0],
        );

        $this->assertStringStartsWith('payment_', $result);
    }

    #[Test]
    public function it_retries_on_transient_failure(): void
    {
        // FailingActivity::failOnce throws on first call, succeeds on second
        $failingInstance = new FailingActivity();
        $this->executor->registerInstance(FailingActivity::class, $failingInstance);

        $retryPolicy = new RetryPolicy(maxAttempts: 3);

        $result = $this->executor->execute(
            FailingActivity::class,
            'failOnce',
            [],
            $retryPolicy,
        );

        $this->assertSame('success_after_retry', $result);
    }

    #[Test]
    public function it_throws_after_max_retries_exhausted(): void
    {
        $retryPolicy = new RetryPolicy(maxAttempts: 2);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Permanent failure');

        $this->executor->execute(
            FailingActivity::class,
            'alwaysFail',
            [],
            $retryPolicy,
        );
    }

    #[Test]
    public function it_does_not_retry_non_retryable_exceptions(): void
    {
        $retryPolicy = new RetryPolicy(
            maxAttempts: 5,
            nonRetryableExceptions: [\InvalidArgumentException::class],
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Bad input');

        $this->executor->execute(
            FailingActivity::class,
            'failWithNonRetryable',
            [],
            $retryPolicy,
        );
    }

    #[Test]
    public function it_uses_registered_instance(): void
    {
        $mockActivity = new PaymentActivity();
        $this->executor->registerInstance(PaymentActivity::class, $mockActivity);

        $result = $this->executor->execute(
            PaymentActivity::class,
            'charge',
            [50.0],
        );

        $this->assertStringStartsWith('payment_', $result);
    }

    #[Test]
    public function it_throws_for_nonexistent_method(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('does not exist');

        $this->executor->execute(
            PaymentActivity::class,
            'nonExistentMethod',
            [],
        );
    }

    #[Test]
    public function it_defaults_to_three_retry_attempts(): void
    {
        // Default RetryPolicy has maxAttempts=3
        // alwaysFail throws every time, so after 3 attempts it should throw
        $this->expectException(\RuntimeException::class);

        $this->executor->execute(
            FailingActivity::class,
            'alwaysFail',
            [],
        );
    }
}
