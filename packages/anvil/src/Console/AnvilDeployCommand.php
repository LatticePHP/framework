<?php

declare(strict_types=1);

namespace Lattice\Anvil\Console;

use Lattice\Anvil\Deploy\Deployer;
use Lattice\Anvil\Deploy\DeploymentResult;
use Lattice\Anvil\Deploy\Steps\CacheClearStep;
use Lattice\Anvil\Deploy\Steps\ComposerInstallStep;
use Lattice\Anvil\Deploy\Steps\GitPullStep;
use Lattice\Anvil\Deploy\Steps\MigrateStep;
use Lattice\Anvil\Deploy\Steps\QueueRestartStep;
use Lattice\Core\Console\LatticeStyle;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

final class AnvilDeployCommand extends Command
{
    private ?Deployer $deployer = null;

    public function __construct(?Deployer $deployer = null)
    {
        $this->deployer = $deployer;
        parent::__construct('deploy');
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Run the full deployment pipeline')
            ->addOption('branch', 'b', InputOption::VALUE_OPTIONAL, 'The branch to deploy', 'main')
            ->addOption('path', 'p', InputOption::VALUE_OPTIONAL, 'The project path', null)
            ->addOption('skip-migrate', null, InputOption::VALUE_NONE, 'Skip database migrations')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Skip confirmation prompt');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $style = new LatticeStyle($output);
        $style->banner();
        $style->header('Anvil — Deploy');
        $style->newLine();

        $projectPath = (string) ($input->getOption('path') ?? getcwd());
        $branch = (string) $input->getOption('branch');
        $skipMigrate = (bool) $input->getOption('skip-migrate');

        // Pre-flight checks
        $style->info('Running pre-flight checks...');
        $style->newLine();

        $preflightOk = $this->runPreflightChecks($style, $projectPath, $branch);

        if (!$preflightOk) {
            $style->error('Pre-flight checks failed. Aborting deployment.');
            $style->newLine();

            return Command::FAILURE;
        }

        // Build the deployer
        $deployer = $this->deployer ?? $this->buildDeployer($projectPath, $branch, $skipMigrate);

        // Hook into step events for progress output
        $deployer->onStepStart(function (string $name) use ($style): void {
            $style->info("Executing: {$name}...");
        });

        $deployer->onStepComplete(function (string $name, bool $success) use ($style): void {
            if ($success) {
                $style->success($name);
            } else {
                $style->error($name);
            }
        });

        // Execute deployment
        $style->newLine();
        $style->info('Starting deployment...');
        $style->newLine();

        $result = $deployer->deploy();

        $style->newLine();
        $this->showSummary($style, $result);

        if (!$result->success) {
            $style->warning('Deployment failed. Rolling back...');
            $style->newLine();

            $rolledBack = $deployer->rollback();

            foreach ($rolledBack as $stepName) {
                $style->info("Rolled back: {$stepName}");
            }

            $style->newLine();
            $style->error('Deployment rolled back.');
            $style->newLine();

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function runPreflightChecks(LatticeStyle $style, string $projectPath, string $branch): bool
    {
        $allOk = true;

        // Check git status is clean
        $process = new Process(['git', 'status', '--porcelain'], $projectPath);
        $process->run();

        if ($process->isSuccessful() && trim($process->getOutput()) === '') {
            $style->success('Working directory is clean');
        } else {
            $style->warning('Working directory has uncommitted changes');
        }

        // Check current branch
        $process = new Process(['git', 'branch', '--show-current'], $projectPath);
        $process->run();

        if ($process->isSuccessful()) {
            $currentBranch = trim($process->getOutput());
            $style->keyValue('Current branch', $currentBranch);
        } else {
            $style->error('Could not determine current branch');
            $allOk = false;
        }

        // Check remote
        $process = new Process(['git', 'remote', '-v'], $projectPath);
        $process->run();

        if ($process->isSuccessful() && trim($process->getOutput()) !== '') {
            $style->success('Remote configured');
        } else {
            $style->error('No git remote configured');
            $allOk = false;
        }

        $style->keyValue('Target branch', $branch);

        return $allOk;
    }

    private function buildDeployer(string $projectPath, string $branch, bool $skipMigrate): Deployer
    {
        $deployer = new Deployer();

        $deployer->addStep(new GitPullStep($projectPath, $branch));
        $deployer->addStep(new ComposerInstallStep($projectPath));

        if (!$skipMigrate) {
            $deployer->addStep(new MigrateStep($projectPath));
        }

        $deployer->addStep(new CacheClearStep($projectPath));
        $deployer->addStep(new QueueRestartStep($projectPath));

        return $deployer;
    }

    private function showSummary(LatticeStyle $style, DeploymentResult $result): void
    {
        $style->header('Deployment Summary');
        $style->newLine();

        $statusLabel = $result->success
            ? '<fg=green>SUCCESS</>'
            : '<fg=red>FAILED</>';

        $style->keyValue('Status', $statusLabel);
        $style->keyValue('Duration', number_format($result->duration, 2) . 's');
        $style->keyValue('Steps completed', (string) count($result->completedSteps));

        if ($result->completedSteps !== []) {
            $style->newLine();
            foreach ($result->completedSteps as $step) {
                $style->success($step);
            }
        }

        if ($result->errors !== []) {
            $style->newLine();
            foreach ($result->errors as $error) {
                $style->error($error);
            }
        }

        $style->newLine();
    }
}
