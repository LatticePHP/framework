<?php

declare(strict_types=1);

namespace Lattice\Core\Console\Commands;

use Lattice\Core\Console\LatticeStyle;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class MakeWorkflowCommand extends Command
{
    public function __construct()
    {
        parent::__construct('make:workflow');
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Create a new workflow with activity classes')
            ->addArgument('name', InputArgument::REQUIRED, 'The workflow name (e.g., OrderFulfillment)')
            ->addOption('activities', 'a', InputOption::VALUE_OPTIONAL, 'Comma-separated activity names', '')
            ->addOption('module', 'm', InputOption::VALUE_OPTIONAL, 'The module to create the workflow in');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = (string) $input->getArgument('name');
        $activitiesStr = (string) $input->getOption('activities');
        $module = $input->getOption('module');
        $style = new LatticeStyle($output);

        $style->banner();
        $style->header("Creating workflow: {$name}");
        $style->newLine();

        $workflowClass = str_ends_with($name, 'Workflow') ? $name : $name . 'Workflow';
        $workflowNamespace = $module ? "App\\Modules\\{$module}\\Workflows" : 'App\\Workflows';
        $activityNamespace = $module ? "App\\Modules\\{$module}\\Activities" : 'App\\Activities';
        $workflowPath = $module ? "src/Modules/{$module}/Workflows" : 'app/Workflows';
        $activityPath = $module ? "src/Modules/{$module}/Activities" : 'app/Activities';

        $activities = $activitiesStr !== '' ? array_map('trim', explode(',', $activitiesStr)) : [];

        if (!is_dir($workflowPath)) {
            mkdir($workflowPath, 0755, true);
        }

        $activityCalls = $activities !== []
            ? implode("\n", array_map(
                fn(string $a): string => "        \$context->executeActivity({$a}Activity::class, '{$a}');",
                $activities,
            ))
            : '        // Add activity calls here';

        $workflowContent = <<<PHP
            <?php

            declare(strict_types=1);

            namespace {$workflowNamespace};

            use Lattice\\Workflow\\Attributes\\Workflow;
            use Lattice\\Workflow\\Runtime\\WorkflowContext;

            #[Workflow(name: '{$name}')]
            final class {$workflowClass}
            {
                public function execute(WorkflowContext \$context): mixed
                {
            {$activityCalls}

                    return ['status' => 'completed'];
                }
            }
            PHP;

        $workflowFile = $workflowPath . '/' . $workflowClass . '.php';
        file_put_contents($workflowFile, $workflowContent);
        $style->success("Workflow created at <fg=white>{$workflowFile}</>");

        // Create activity classes
        if ($activities !== []) {
            if (!is_dir($activityPath)) {
                mkdir($activityPath, 0755, true);
            }

            foreach ($activities as $activity) {
                $activityClass = $activity . 'Activity';
                $activityContent = <<<PHP
                    <?php

                    declare(strict_types=1);

                    namespace {$activityNamespace};

                    use Lattice\\Workflow\\Attributes\\Activity;

                    #[Activity(name: '{$activity}')]
                    final class {$activityClass}
                    {
                        public function execute(mixed \$input = null): mixed
                        {
                            // Implement {$activity} logic
                            return ['result' => '{$activity} completed'];
                        }
                    }
                    PHP;

                $activityFile = $activityPath . '/' . $activityClass . '.php';
                file_put_contents($activityFile, $activityContent);
                $style->success("Activity created at <fg=white>{$activityFile}</>");
            }
        }

        // Create test file
        $testPath = $module ? "tests/Modules/{$module}/Workflows" : 'tests/Workflows';
        if (!is_dir($testPath)) {
            mkdir($testPath, 0755, true);
        }

        $testContent = <<<PHP
            <?php

            declare(strict_types=1);

            namespace Tests\\Workflows;

            use PHPUnit\\Framework\\Attributes\\Test;
            use PHPUnit\\Framework\\TestCase;

            final class {$workflowClass}Test extends TestCase
            {
                #[Test]
                public function it_can_be_instantiated(): void
                {
                    \$workflow = new \\{$workflowNamespace}\\{$workflowClass}();
                    \$this->assertInstanceOf(\\{$workflowNamespace}\\{$workflowClass}::class, \$workflow);
                }
            }
            PHP;

        $testFile = $testPath . '/' . $workflowClass . 'Test.php';
        file_put_contents($testFile, $testContent);
        $style->success("Test created at <fg=white>{$testFile}</>");

        $style->newLine();
        $style->info("Workflow <fg=white>{$name}</> created with " . count($activities) . " activities");
        $style->newLine();

        return Command::SUCCESS;
    }
}
