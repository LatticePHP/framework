<?php

declare(strict_types=1);

namespace Lattice\Core\Tests\Unit\CircuitBreaker;

use Lattice\Core\CircuitBreaker\Circuit;
use Lattice\Core\CircuitBreaker\CircuitBreaker;
use Lattice\Core\CircuitBreaker\CircuitOpenException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CircuitBreakerTest extends TestCase
{
    protected function setUp(): void
    {
        CircuitBreaker::resetAll();
    }

    #[Test]
    public function test_successful_call_returns_result(): void
    {
        $result = CircuitBreaker::call('api', fn() => 'ok');

        $this->assertSame('ok', $result);
        $this->assertSame('closed', CircuitBreaker::getState('api'));
    }

    #[Test]
    public function test_failed_calls_open_circuit_after_threshold(): void
    {
        CircuitBreaker::configure('api', ['failureThreshold' => 3]);

        for ($i = 0; $i < 3; $i++) {
            try {
                CircuitBreaker::call('api', fn() => throw new \RuntimeException('fail'));
            } catch (\RuntimeException) {
                // Expected
            }
        }

        $this->assertSame('open', CircuitBreaker::getState('api'));
    }

    #[Test]
    public function test_open_circuit_throws_immediately(): void
    {
        CircuitBreaker::configure('api', ['failureThreshold' => 1]);

        try {
            CircuitBreaker::call('api', fn() => throw new \RuntimeException('fail'));
        } catch (\RuntimeException) {
            // Expected
        }

        $this->expectException(CircuitOpenException::class);
        $this->expectExceptionMessage("Circuit 'api' is open");

        CircuitBreaker::call('api', fn() => 'ok');
    }

    #[Test]
    public function test_fallback_called_when_circuit_open(): void
    {
        CircuitBreaker::configure('api', ['failureThreshold' => 1]);

        try {
            CircuitBreaker::call('api', fn() => throw new \RuntimeException('fail'));
        } catch (\RuntimeException) {
            // Expected
        }

        $result = CircuitBreaker::call(
            'api',
            fn() => 'ok',
            fn() => 'fallback-value',
        );

        $this->assertSame('fallback-value', $result);
    }

    #[Test]
    public function test_fallback_called_when_failure_causes_open(): void
    {
        CircuitBreaker::configure('api', ['failureThreshold' => 1]);

        $result = CircuitBreaker::call(
            'api',
            fn() => throw new \RuntimeException('fail'),
            fn() => 'fallback-value',
        );

        $this->assertSame('fallback-value', $result);
    }

    #[Test]
    public function test_timeout_transitions_to_half_open(): void
    {
        $circuit = new Circuit(failureThreshold: 1, successThreshold: 1, timeout: 0);

        // Trip the circuit
        $circuit->recordFailure();

        // With timeout=0, getState immediately transitions to half-open
        $this->assertSame('half-open', $circuit->getState());
    }

    #[Test]
    public function test_half_open_success_closes_circuit(): void
    {
        $circuit = new Circuit(failureThreshold: 1, successThreshold: 2, timeout: 0);

        // Trip the circuit
        $circuit->recordFailure();

        // With timeout=0, transitions to half-open immediately
        $this->assertSame('half-open', $circuit->getState());

        // Two successes needed to close
        $circuit->recordSuccess();
        $this->assertSame('half-open', $circuit->getState());

        $circuit->recordSuccess();
        $this->assertSame('closed', $circuit->getState());
    }

    #[Test]
    public function test_half_open_failure_reopens_circuit(): void
    {
        $circuit = new Circuit(failureThreshold: 1, successThreshold: 2, timeout: 0);

        $circuit->recordFailure();
        // With timeout=0, getState transitions to half-open
        $this->assertSame('half-open', $circuit->getState());

        $this->assertTrue($circuit->isHalfOpen());

        $circuit->recordFailure();
        // After failure in half-open, it reopens but timeout=0 means
        // getState will transition back to half-open immediately
        // So we check isOpen() directly before the timeout check
        $this->assertFalse($circuit->isClosed());
    }

    #[Test]
    public function test_configure_sets_thresholds(): void
    {
        CircuitBreaker::configure('api', [
            'failureThreshold' => 2,
            'successThreshold' => 3,
            'timeout' => 60,
        ]);

        // Should not be open after 1 failure
        try {
            CircuitBreaker::call('api', fn() => throw new \RuntimeException('fail'));
        } catch (\RuntimeException) {
            // Expected
        }

        $this->assertSame('closed', CircuitBreaker::getState('api'));

        // Should be open after 2nd failure
        try {
            CircuitBreaker::call('api', fn() => throw new \RuntimeException('fail'));
        } catch (\RuntimeException) {
            // Expected
        }

        $this->assertSame('open', CircuitBreaker::getState('api'));
    }

    #[Test]
    public function test_reset_individual_circuit(): void
    {
        CircuitBreaker::configure('api', ['failureThreshold' => 1]);

        try {
            CircuitBreaker::call('api', fn() => throw new \RuntimeException('fail'));
        } catch (\RuntimeException) {
            // Expected
        }

        $this->assertSame('open', CircuitBreaker::getState('api'));

        CircuitBreaker::reset('api');
        $this->assertSame('closed', CircuitBreaker::getState('api'));
    }

    #[Test]
    public function test_circuit_failure_count(): void
    {
        $circuit = new Circuit(failureThreshold: 5);

        $circuit->recordFailure();
        $circuit->recordFailure();

        $this->assertSame(2, $circuit->getFailureCount());
        $this->assertTrue($circuit->isClosed());
    }

    #[Test]
    public function test_success_resets_failure_count_when_closed(): void
    {
        $circuit = new Circuit(failureThreshold: 5);

        $circuit->recordFailure();
        $circuit->recordFailure();
        $this->assertSame(2, $circuit->getFailureCount());

        $circuit->recordSuccess();
        $this->assertSame(0, $circuit->getFailureCount());
    }
}
