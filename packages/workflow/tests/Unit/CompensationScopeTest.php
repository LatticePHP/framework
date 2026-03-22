<?php

declare(strict_types=1);

namespace Lattice\Workflow\Tests\Unit;

use Lattice\Workflow\Compensation\CompensationException;
use Lattice\Workflow\Compensation\CompensationScope;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CompensationScopeTest extends TestCase
{
    #[Test]
    public function it_runs_compensations_in_reverse_order(): void
    {
        $scope = new CompensationScope();
        $order = [];

        $scope->addCompensation(function () use (&$order) { $order[] = 'first'; });
        $scope->addCompensation(function () use (&$order) { $order[] = 'second'; });
        $scope->addCompensation(function () use (&$order) { $order[] = 'third'; });

        $scope->compensate();

        $this->assertSame(['third', 'second', 'first'], $order);
    }

    #[Test]
    public function it_runs_action_and_registers_compensation(): void
    {
        $scope = new CompensationScope();
        $compensated = false;

        $result = $scope->run(
            fn () => 'action_result',
            function () use (&$compensated) { $compensated = true; },
        );

        $this->assertSame('action_result', $result);
        $this->assertFalse($compensated); // Not compensated yet

        $scope->compensate();
        $this->assertTrue($compensated);
    }

    #[Test]
    public function it_does_not_register_compensation_when_action_fails(): void
    {
        $scope = new CompensationScope();
        $compensated = false;

        try {
            $scope->run(
                fn () => throw new \RuntimeException('Action failed'),
                function () use (&$compensated) { $compensated = true; },
            );
        } catch (\RuntimeException) {
            // Expected
        }

        // Compensation should NOT be registered since the action failed
        $scope->compensate();
        $this->assertFalse($compensated);
    }

    #[Test]
    public function it_collects_errors_when_compensations_fail(): void
    {
        $scope = new CompensationScope();

        $scope->addCompensation(fn () => null); // succeeds
        $scope->addCompensation(fn () => throw new \RuntimeException('Comp 2 failed'));
        $scope->addCompensation(fn () => throw new \RuntimeException('Comp 3 failed'));

        try {
            $scope->compensate();
            $this->fail('Expected CompensationException');
        } catch (CompensationException $e) {
            $failures = $e->getFailures();
            $this->assertCount(2, $failures);
            $this->assertSame('Comp 3 failed', $failures[0]->getMessage()); // Reverse order
            $this->assertSame('Comp 2 failed', $failures[1]->getMessage());
        }
    }

    #[Test]
    public function it_runs_all_compensations_even_if_some_fail(): void
    {
        $scope = new CompensationScope();
        $executed = [];

        $scope->addCompensation(function () use (&$executed) { $executed[] = 1; });
        $scope->addCompensation(function () use (&$executed) {
            $executed[] = 2;
            throw new \RuntimeException('fail');
        });
        $scope->addCompensation(function () use (&$executed) { $executed[] = 3; });

        try {
            $scope->compensate();
        } catch (CompensationException) {
            // Expected
        }

        // All three compensations should have been attempted (in reverse: 3, 2, 1)
        $this->assertSame([3, 2, 1], $executed);
    }

    #[Test]
    public function it_handles_empty_compensations(): void
    {
        $scope = new CompensationScope();

        // Should not throw
        $scope->compensate();
        $this->assertTrue(true);
    }

    #[Test]
    public function it_supports_saga_pattern_with_multiple_steps(): void
    {
        $scope = new CompensationScope();
        $results = [];
        $compensations = [];

        $results[] = $scope->run(
            fn () => 'step_1_result',
            function () use (&$compensations) { $compensations[] = 'undo_step_1'; },
        );

        $results[] = $scope->run(
            fn () => 'step_2_result',
            function () use (&$compensations) { $compensations[] = 'undo_step_2'; },
        );

        $this->assertSame(['step_1_result', 'step_2_result'], $results);

        // Simulate failure after step 2 — compensate
        $scope->compensate();

        // Compensations run in reverse
        $this->assertSame(['undo_step_2', 'undo_step_1'], $compensations);
    }
}
