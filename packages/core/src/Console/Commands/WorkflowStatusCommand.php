<?php

declare(strict_types=1);

namespace Lattice\Core\Console\Commands;

use Lattice\Contracts\Workflow\WorkflowEventStoreInterface;
use Lattice\Contracts\Workflow\WorkflowStatus;
use Lattice\Core\Console\LatticeStyle;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class WorkflowStatusCommand extends Command
{
    public function __construct(
        private readonly WorkflowEventStoreInterface $eventStore,
    ) {
        parent::__construct('workflow:status');
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Show workflow execution status by ID')
            ->addArgument('id', InputArgument::REQUIRED, 'The workflow ID')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Output as JSON');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $workflowId = (string) $input->getArgument('id');

        $style = new LatticeStyle($output);

        $execution = $this->eventStore->findExecutionByWorkflowId($workflowId);

        if ($execution === null) {
            if ($input->getOption('json')) {
                $output->writeln((string) json_encode(['error' => "No execution found for workflow: {$workflowId}"], JSON_PRETTY_PRINT));

                return Command::FAILURE;
            }

            $style->banner();
            $style->header('Workflow Status');
            $style->newLine();
            $style->error("No execution found for workflow: {$workflowId}");
            $style->newLine();

            return Command::FAILURE;
        }

        $status = $execution->getStatus();
        $startedAt = $execution->getStartedAt();
        $completedAt = $execution->getCompletedAt();

        if ($input->getOption('json')) {
            $data = [
                'workflowId' => $execution->getWorkflowId(),
                'runId' => $execution->getRunId(),
                'type' => $execution->getWorkflowType(),
                'status' => $status->value,
                'startedAt' => $startedAt->format('Y-m-d H:i:s'),
                'completedAt' => $completedAt?->format('Y-m-d H:i:s'),
            ];

            $output->writeln((string) json_encode($data, JSON_PRETTY_PRINT));

            return Command::SUCCESS;
        }

        $style->banner();
        $style->header('Workflow Status');
        $style->newLine();

        $statusColor = match ($status) {
            WorkflowStatus::Running => 'blue',
            WorkflowStatus::Completed => 'green',
            WorkflowStatus::Failed => 'red',
            WorkflowStatus::Cancelled => 'yellow',
            WorkflowStatus::Terminated => 'red',
            WorkflowStatus::TimedOut => 'yellow',
        };

        $style->keyValue('Workflow ID', $execution->getWorkflowId());
        $style->keyValue('Run ID', $execution->getRunId());
        $style->keyValue('Type', $execution->getWorkflowType());
        $style->keyValue('Status', "<fg={$statusColor}>{$status->value}</>");
        $style->keyValue('Started At', $startedAt->format('Y-m-d H:i:s'));

        if ($completedAt !== null) {
            $style->keyValue('Completed At', $completedAt->format('Y-m-d H:i:s'));

            $duration = $completedAt->getTimestamp() - $startedAt->getTimestamp();
            $style->keyValue('Duration', $duration . 's');
        }

        $style->newLine();

        return Command::SUCCESS;
    }
}
