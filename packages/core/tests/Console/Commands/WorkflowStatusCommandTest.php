<?php

declare(strict_types=1);

namespace Lattice\Core\Tests\Console\Commands;

use Lattice\Contracts\Workflow\WorkflowEventStoreInterface;
use Lattice\Contracts\Workflow\WorkflowExecutionInterface;
use Lattice\Contracts\Workflow\WorkflowStatus;
use Lattice\Core\Console\Commands\WorkflowStatusCommand;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class WorkflowStatusCommandTest extends TestCase
{
    #[Test]
    public function it_constructs_with_correct_name(): void
    {
        $eventStore = $this->createMock(WorkflowEventStoreInterface::class);
        $command = new WorkflowStatusCommand($eventStore);

        $this->assertSame('workflow:status', $command->getName());
        $this->assertSame('Show workflow execution status by ID', $command->getDescription());
    }

    #[Test]
    public function it_has_required_id_argument(): void
    {
        $eventStore = $this->createMock(WorkflowEventStoreInterface::class);
        $command = new WorkflowStatusCommand($eventStore);

        $this->assertTrue($command->getDefinition()->hasArgument('id'));
        $this->assertTrue($command->getDefinition()->getArgument('id')->isRequired());
    }

    #[Test]
    public function it_has_json_option(): void
    {
        $eventStore = $this->createMock(WorkflowEventStoreInterface::class);
        $command = new WorkflowStatusCommand($eventStore);

        $this->assertTrue($command->getDefinition()->hasOption('json'));
    }

    #[Test]
    public function it_shows_error_when_workflow_not_found(): void
    {
        $eventStore = $this->createMock(WorkflowEventStoreInterface::class);
        $eventStore->method('findExecutionByWorkflowId')
            ->willReturn(null);

        $command = new WorkflowStatusCommand($eventStore);
        $tester = new CommandTester($command);
        $tester->execute(['id' => 'nonexistent-id']);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('No execution found', $tester->getDisplay());
    }

    #[Test]
    public function it_shows_workflow_status(): void
    {
        $startedAt = new \DateTimeImmutable('2025-01-15 10:00:00');
        $completedAt = new \DateTimeImmutable('2025-01-15 10:05:30');

        $execution = $this->createMock(WorkflowExecutionInterface::class);
        $execution->method('getWorkflowId')->willReturn('wf-123');
        $execution->method('getRunId')->willReturn('run-456');
        $execution->method('getWorkflowType')->willReturn('OrderWorkflow');
        $execution->method('getStatus')->willReturn(WorkflowStatus::Completed);
        $execution->method('getStartedAt')->willReturn($startedAt);
        $execution->method('getCompletedAt')->willReturn($completedAt);

        $eventStore = $this->createMock(WorkflowEventStoreInterface::class);
        $eventStore->method('findExecutionByWorkflowId')
            ->with('wf-123')
            ->willReturn($execution);

        $command = new WorkflowStatusCommand($eventStore);
        $tester = new CommandTester($command);
        $tester->execute(['id' => 'wf-123']);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());

        $display = $tester->getDisplay();
        $this->assertStringContainsString('wf-123', $display);
        $this->assertStringContainsString('run-456', $display);
        $this->assertStringContainsString('OrderWorkflow', $display);
        $this->assertStringContainsString('completed', $display);
        $this->assertStringContainsString('2025-01-15 10:00:00', $display);
        $this->assertStringContainsString('2025-01-15 10:05:30', $display);
        $this->assertStringContainsString('Duration', $display);
    }

    #[Test]
    public function it_shows_running_workflow_without_completed_at(): void
    {
        $startedAt = new \DateTimeImmutable('2025-01-15 10:00:00');

        $execution = $this->createMock(WorkflowExecutionInterface::class);
        $execution->method('getWorkflowId')->willReturn('wf-789');
        $execution->method('getRunId')->willReturn('run-101');
        $execution->method('getWorkflowType')->willReturn('PaymentWorkflow');
        $execution->method('getStatus')->willReturn(WorkflowStatus::Running);
        $execution->method('getStartedAt')->willReturn($startedAt);
        $execution->method('getCompletedAt')->willReturn(null);

        $eventStore = $this->createMock(WorkflowEventStoreInterface::class);
        $eventStore->method('findExecutionByWorkflowId')
            ->with('wf-789')
            ->willReturn($execution);

        $command = new WorkflowStatusCommand($eventStore);
        $tester = new CommandTester($command);
        $tester->execute(['id' => 'wf-789']);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());

        $display = $tester->getDisplay();
        $this->assertStringContainsString('running', $display);
        $this->assertStringNotContainsString('Duration', $display);
    }

    #[Test]
    public function it_outputs_json(): void
    {
        $startedAt = new \DateTimeImmutable('2025-01-15 10:00:00');

        $execution = $this->createMock(WorkflowExecutionInterface::class);
        $execution->method('getWorkflowId')->willReturn('wf-json');
        $execution->method('getRunId')->willReturn('run-json');
        $execution->method('getWorkflowType')->willReturn('TestWorkflow');
        $execution->method('getStatus')->willReturn(WorkflowStatus::Completed);
        $execution->method('getStartedAt')->willReturn($startedAt);
        $execution->method('getCompletedAt')->willReturn(null);

        $eventStore = $this->createMock(WorkflowEventStoreInterface::class);
        $eventStore->method('findExecutionByWorkflowId')
            ->willReturn($execution);

        $command = new WorkflowStatusCommand($eventStore);
        $tester = new CommandTester($command);
        $tester->execute(['id' => 'wf-json', '--json' => true]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());

        $data = json_decode($tester->getDisplay(), true);
        $this->assertSame('wf-json', $data['workflowId']);
        $this->assertSame('completed', $data['status']);
    }

    #[Test]
    public function it_outputs_json_error_when_not_found(): void
    {
        $eventStore = $this->createMock(WorkflowEventStoreInterface::class);
        $eventStore->method('findExecutionByWorkflowId')->willReturn(null);

        $command = new WorkflowStatusCommand($eventStore);
        $tester = new CommandTester($command);
        $tester->execute(['id' => 'missing', '--json' => true]);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());

        $data = json_decode($tester->getDisplay(), true);
        $this->assertArrayHasKey('error', $data);
    }
}
