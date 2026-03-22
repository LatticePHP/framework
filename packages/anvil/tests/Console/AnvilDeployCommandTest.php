<?php

declare(strict_types=1);

namespace Lattice\Anvil\Tests\Console;

use Lattice\Anvil\Console\AnvilDeployCommand;
use Lattice\Anvil\Deploy\Deployer;
use Lattice\Anvil\Deploy\DeployStep;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

final class AnvilDeployCommandTest extends TestCase
{
    public function test_command_name(): void
    {
        $command = new AnvilDeployCommand();
        $this->assertSame('deploy', $command->getName());
    }

    public function test_successful_deployment_output(): void
    {
        $deployer = new Deployer();
        $deployer->addStep($this->createSuccessStep('Step 1'));
        $deployer->addStep($this->createSuccessStep('Step 2'));

        $command = new AnvilDeployCommand($deployer);

        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute(['--path' => __DIR__]);

        $output = $tester->getDisplay();

        $this->assertStringContainsString('Deployment Summary', $output);
        $this->assertStringContainsString('Step 1', $output);
        $this->assertStringContainsString('Step 2', $output);
        $this->assertSame(0, $tester->getStatusCode());
    }

    public function test_failed_deployment_shows_rollback(): void
    {
        $deployer = new Deployer();
        $deployer->addStep($this->createSuccessStep('Step 1'));
        $deployer->addStep($this->createFailingStep('Step 2', 'Test failure'));

        $command = new AnvilDeployCommand($deployer);

        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute(['--path' => __DIR__]);

        $output = $tester->getDisplay();

        $this->assertStringContainsString('Rolling back', $output);
        $this->assertStringContainsString('rolled back', $output);
        $this->assertSame(1, $tester->getStatusCode());
    }

    public function test_deploy_command_has_branch_option(): void
    {
        $command = new AnvilDeployCommand();
        $definition = $command->getDefinition();

        $this->assertTrue($definition->hasOption('branch'));
        $this->assertSame('main', $definition->getOption('branch')->getDefault());
    }

    public function test_deploy_command_has_path_option(): void
    {
        $command = new AnvilDeployCommand();
        $definition = $command->getDefinition();

        $this->assertTrue($definition->hasOption('path'));
    }

    public function test_deploy_command_has_skip_migrate_option(): void
    {
        $command = new AnvilDeployCommand();
        $definition = $command->getDefinition();

        $this->assertTrue($definition->hasOption('skip-migrate'));
    }

    public function test_deploy_command_has_force_option(): void
    {
        $command = new AnvilDeployCommand();
        $definition = $command->getDefinition();

        $this->assertTrue($definition->hasOption('force'));
    }

    public function test_deploy_shows_pre_flight_checks(): void
    {
        $deployer = new Deployer();
        $deployer->addStep($this->createSuccessStep('Step 1'));

        $command = new AnvilDeployCommand($deployer);

        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute(['--path' => __DIR__]);

        $output = $tester->getDisplay();
        $this->assertStringContainsString('pre-flight', $output);
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
            }

            public function rollback(): void
            {
            }
        };
    }

    private function createFailingStep(string $name, string $error): DeployStep
    {
        return new class($name, $error) implements DeployStep {
            public function __construct(
                private readonly string $stepName,
                private readonly string $error,
            ) {
            }

            public function name(): string
            {
                return $this->stepName;
            }

            public function execute(): void
            {
                throw new \RuntimeException($this->error);
            }

            public function rollback(): void
            {
            }
        };
    }
}
