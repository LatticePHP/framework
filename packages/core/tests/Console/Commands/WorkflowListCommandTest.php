<?php

declare(strict_types=1);

namespace Lattice\Core\Tests\Console\Commands;

use Lattice\Core\Console\Commands\WorkflowListCommand;
use Lattice\Workflow\Attributes\Activity;
use Lattice\Workflow\Attributes\Workflow;
use Lattice\Workflow\Registry\WorkflowRegistry;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

#[Workflow(name: 'OrderWorkflow')]
final class StubOrderWorkflow
{
    public function execute(): void {}
}

#[Activity(name: 'ValidateOrder')]
final class StubValidateOrderActivity
{
    public function execute(): void {}
}

final class WorkflowListCommandTest extends TestCase
{
    #[Test]
    public function it_constructs_with_correct_name(): void
    {
        $registry = new WorkflowRegistry();
        $command = new WorkflowListCommand($registry);

        $this->assertSame('workflow:list', $command->getName());
        $this->assertSame('List all registered workflows and activities', $command->getDescription());
    }

    #[Test]
    public function it_has_json_option(): void
    {
        $registry = new WorkflowRegistry();
        $command = new WorkflowListCommand($registry);

        $this->assertTrue($command->getDefinition()->hasOption('json'));
    }

    #[Test]
    public function it_shows_no_workflows_message_when_empty(): void
    {
        $registry = new WorkflowRegistry();
        $command = new WorkflowListCommand($registry);
        $tester = new CommandTester($command);
        $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('No workflows registered', $tester->getDisplay());
        $this->assertStringContainsString('No activities registered', $tester->getDisplay());
    }

    #[Test]
    public function it_lists_workflows_and_activities_in_table(): void
    {
        $registry = new WorkflowRegistry();
        $registry->registerWorkflow(StubOrderWorkflow::class);
        $registry->registerActivity(StubValidateOrderActivity::class);

        $command = new WorkflowListCommand($registry);
        $tester = new CommandTester($command);
        $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());

        $display = $tester->getDisplay();
        $this->assertStringContainsString('OrderWorkflow', $display);
        $this->assertStringContainsString('ValidateOrder', $display);
        $this->assertStringContainsString('Total workflows: 1', $display);
        $this->assertStringContainsString('Total activities: 1', $display);
    }

    #[Test]
    public function it_outputs_json(): void
    {
        $registry = new WorkflowRegistry();
        $registry->registerWorkflow(StubOrderWorkflow::class);
        $registry->registerActivity(StubValidateOrderActivity::class);

        $command = new WorkflowListCommand($registry);
        $tester = new CommandTester($command);
        $tester->execute(['--json' => true]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());

        $data = json_decode($tester->getDisplay(), true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('workflows', $data);
        $this->assertArrayHasKey('activities', $data);
        $this->assertArrayHasKey('OrderWorkflow', $data['workflows']);
        $this->assertArrayHasKey('ValidateOrder', $data['activities']);
    }
}
