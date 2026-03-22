<?php

declare(strict_types=1);

namespace Lattice\Core\Console\Commands;

use Lattice\Core\Console\LatticeStyle;
use Lattice\Workflow\Registry\WorkflowRegistry;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class WorkflowListCommand extends Command
{
    public function __construct(
        private readonly WorkflowRegistry $registry,
    ) {
        parent::__construct('workflow:list');
    }

    protected function configure(): void
    {
        $this
            ->setDescription('List all registered workflows and activities')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Output as JSON');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $workflows = $this->registry->getRegisteredWorkflows();
        $activities = $this->registry->getRegisteredActivities();

        if ($input->getOption('json')) {
            $output->writeln((string) json_encode([
                'workflows' => $workflows,
                'activities' => $activities,
            ], JSON_PRETTY_PRINT));

            return Command::SUCCESS;
        }

        $style = new LatticeStyle($output);
        $style->banner();
        $style->header('Registered Workflows');
        $style->newLine();

        if ($workflows === []) {
            $style->info('No workflows registered');
        } else {
            $rows = [];
            foreach ($workflows as $name => $class) {
                $rows[] = [
                    "<fg=cyan>{$name}</>",
                    $class,
                ];
            }

            $style->table(
                ['Name', 'Class'],
                $rows,
            );
        }

        $style->newLine();
        $style->header('Registered Activities');
        $style->newLine();

        if ($activities === []) {
            $style->info('No activities registered');
        } else {
            $rows = [];
            foreach ($activities as $name => $class) {
                $rows[] = [
                    "<fg=yellow>{$name}</>",
                    $class,
                ];
            }

            $style->table(
                ['Name', 'Class'],
                $rows,
            );
        }

        $style->newLine();
        $style->info('Total workflows: ' . count($workflows) . ', Total activities: ' . count($activities));
        $style->newLine();

        return Command::SUCCESS;
    }
}
