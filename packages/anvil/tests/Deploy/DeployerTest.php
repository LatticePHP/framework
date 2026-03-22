<?php

declare(strict_types=1);

namespace Lattice\Anvil\Tests\Deploy;

use Lattice\Anvil\Deploy\Deployer;
use Lattice\Anvil\Deploy\DeploymentResult;
use Lattice\Anvil\Deploy\DeployStep;
use PHPUnit\Framework\TestCase;

final class DeployerTest extends TestCase
{
    public function test_successful_deployment(): void
    {
        $step1 = $this->createSuccessStep('Step 1');
        $step2 = $this->createSuccessStep('Step 2');
        $step3 = $this->createSuccessStep('Step 3');

        $deployer = new Deployer();
        $deployer->addStep($step1);
        $deployer->addStep($step2);
        $deployer->addStep($step3);

        $result = $deployer->deploy();

        $this->assertInstanceOf(DeploymentResult::class, $result);
        $this->assertTrue($result->success);
        $this->assertCount(3, $result->completedSteps);
        $this->assertSame(['Step 1', 'Step 2', 'Step 3'], $result->completedSteps);
        $this->assertSame([], $result->errors);
        $this->assertGreaterThanOrEqual(0.0, $result->duration);
    }

    public function test_failed_deployment_stops_at_failure(): void
    {
        $step1 = $this->createSuccessStep('Step 1');
        $step2 = $this->createFailingStep('Step 2', 'Something went wrong');
        $step3 = $this->createSuccessStep('Step 3');

        $deployer = new Deployer();
        $deployer->addStep($step1);
        $deployer->addStep($step2);
        $deployer->addStep($step3);

        $result = $deployer->deploy();

        $this->assertFalse($result->success);
        $this->assertCount(1, $result->completedSteps);
        $this->assertSame(['Step 1'], $result->completedSteps);
        $this->assertCount(1, $result->errors);
        $this->assertStringContainsString('Something went wrong', $result->errors[0]);
    }

    public function test_rollback_calls_completed_steps_in_reverse(): void
    {
        $rollbackOrder = [];

        $step1 = $this->createStepWithRollback('Step 1', $rollbackOrder);
        $step2 = $this->createStepWithRollback('Step 2', $rollbackOrder);
        $step3 = $this->createFailingStep('Step 3', 'Failure');

        $deployer = new Deployer();
        $deployer->addStep($step1);
        $deployer->addStep($step2);
        $deployer->addStep($step3);

        $deployer->deploy();
        $rolledBack = $deployer->rollback();

        $this->assertSame(['Step 2', 'Step 1'], $rolledBack);
        $this->assertSame(['Step 2', 'Step 1'], $rollbackOrder);
    }

    public function test_empty_deployment(): void
    {
        $deployer = new Deployer();
        $result = $deployer->deploy();

        $this->assertTrue($result->success);
        $this->assertSame([], $result->completedSteps);
        $this->assertSame([], $result->errors);
    }

    public function test_on_step_start_callback(): void
    {
        $startedSteps = [];

        $deployer = new Deployer();
        $deployer->addStep($this->createSuccessStep('Step 1'));
        $deployer->addStep($this->createSuccessStep('Step 2'));

        $deployer->onStepStart(function (string $name) use (&$startedSteps): void {
            $startedSteps[] = $name;
        });

        $deployer->deploy();

        $this->assertSame(['Step 1', 'Step 2'], $startedSteps);
    }

    public function test_on_step_complete_callback(): void
    {
        $completedSteps = [];

        $deployer = new Deployer();
        $deployer->addStep($this->createSuccessStep('Step 1'));
        $deployer->addStep($this->createFailingStep('Step 2', 'Error'));

        $deployer->onStepComplete(function (string $name, bool $success) use (&$completedSteps): void {
            $completedSteps[] = ['name' => $name, 'success' => $success];
        });

        $deployer->deploy();

        $this->assertCount(2, $completedSteps);
        $this->assertSame('Step 1', $completedSteps[0]['name']);
        $this->assertTrue($completedSteps[0]['success']);
        $this->assertSame('Step 2', $completedSteps[1]['name']);
        $this->assertFalse($completedSteps[1]['success']);
    }

    public function test_get_steps_returns_all_added_steps(): void
    {
        $step1 = $this->createSuccessStep('Step 1');
        $step2 = $this->createSuccessStep('Step 2');

        $deployer = new Deployer();
        $deployer->addStep($step1);
        $deployer->addStep($step2);

        $this->assertCount(2, $deployer->getSteps());
    }

    public function test_get_completed_steps_after_deploy(): void
    {
        $deployer = new Deployer();
        $deployer->addStep($this->createSuccessStep('Step 1'));
        $deployer->addStep($this->createSuccessStep('Step 2'));

        $this->assertCount(0, $deployer->getCompletedSteps());

        $deployer->deploy();

        $this->assertCount(2, $deployer->getCompletedSteps());
    }

    public function test_rollback_with_failing_rollback_step(): void
    {
        $step1 = $this->createSuccessStep('Step 1');

        // A step that succeeds on execute but throws on rollback
        $step2 = new class implements DeployStep {
            public function name(): string
            {
                return 'Step 2';
            }

            public function execute(): void
            {
                // Success
            }

            public function rollback(): void
            {
                throw new \RuntimeException('Rollback failed');
            }
        };

        $step3 = $this->createFailingStep('Step 3', 'Deploy error');

        $deployer = new Deployer();
        $deployer->addStep($step1);
        $deployer->addStep($step2);
        $deployer->addStep($step3);

        $deployer->deploy();
        $rolledBack = $deployer->rollback();

        // Both steps attempted rollback, step2 was partial
        $this->assertCount(2, $rolledBack);
        $this->assertSame('Step 2 (partial)', $rolledBack[0]);
        $this->assertSame('Step 1', $rolledBack[1]);
    }

    public function test_add_step_returns_self_for_chaining(): void
    {
        $deployer = new Deployer();
        $result = $deployer->addStep($this->createSuccessStep('Step 1'));

        $this->assertSame($deployer, $result);
    }

    public function test_duration_is_measured(): void
    {
        $slowStep = new class implements DeployStep {
            public function name(): string
            {
                return 'Slow Step';
            }

            public function execute(): void
            {
                usleep(10000); // 10ms
            }

            public function rollback(): void
            {
            }
        };

        $deployer = new Deployer();
        $deployer->addStep($slowStep);

        $result = $deployer->deploy();

        $this->assertGreaterThan(0.005, $result->duration);
    }

    private function createSuccessStep(string $name): DeployStep
    {
        return new class($name) implements DeployStep {
            public function __construct(private readonly string $stepName)
            {
            }

            public function name(): string
            {
                return $this->stepName;
            }

            public function execute(): void
            {
                // Success
            }

            public function rollback(): void
            {
                // No-op
            }
        };
    }

    private function createFailingStep(string $name, string $errorMessage): DeployStep
    {
        return new class($name, $errorMessage) implements DeployStep {
            public function __construct(
                private readonly string $stepName,
                private readonly string $errorMessage,
            ) {
            }

            public function name(): string
            {
                return $this->stepName;
            }

            public function execute(): void
            {
                throw new \RuntimeException($this->errorMessage);
            }

            public function rollback(): void
            {
                // No-op
            }
        };
    }

    /**
     * @param list<string> &$rollbackOrder
     */
    private function createStepWithRollback(string $name, array &$rollbackOrder): DeployStep
    {
        return new class($name, $rollbackOrder) implements DeployStep {
            /** @param list<string> &$rollbackOrder */
            public function __construct(
                private readonly string $stepName,
                private array &$rollbackOrder,
            ) {
            }

            public function name(): string
            {
                return $this->stepName;
            }

            public function execute(): void
            {
                // Success
            }

            public function rollback(): void
            {
                $this->rollbackOrder[] = $this->stepName;
            }
        };
    }
}
