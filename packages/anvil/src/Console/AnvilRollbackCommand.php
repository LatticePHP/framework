<?php

declare(strict_types=1);

namespace Lattice\Anvil\Console;

use Lattice\Core\Console\LatticeStyle;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

final class AnvilRollbackCommand extends Command
{
    public function __construct()
    {
        parent::__construct('deploy:rollback');
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Rollback to the previous release')
            ->addOption('path', 'p', InputOption::VALUE_OPTIONAL, 'The project path', null)
            ->addOption('steps', 's', InputOption::VALUE_OPTIONAL, 'Number of commits to roll back', '1');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $style = new LatticeStyle($output);
        $style->banner();
        $style->header('Anvil — Rollback');
        $style->newLine();

        $projectPath = (string) ($input->getOption('path') ?? getcwd());
        $steps = (int) $input->getOption('steps');

        // Get current HEAD before rollback
        $process = new Process(['git', 'rev-parse', '--short', 'HEAD'], $projectPath);
        $process->run();
        $currentHead = $process->isSuccessful() ? trim($process->getOutput()) : 'unknown';

        $style->keyValue('Current HEAD', $currentHead);
        $style->keyValue('Rolling back', "{$steps} commit(s)");
        $style->newLine();

        // Reset to previous commit
        $process = new Process(
            ['git', 'reset', '--hard', "HEAD~{$steps}"],
            $projectPath,
        );
        $process->setTimeout(30);
        $process->run();

        if (!$process->isSuccessful()) {
            $style->error('Git rollback failed: ' . $process->getErrorOutput());
            $style->newLine();

            return Command::FAILURE;
        }

        $style->success('Git rolled back successfully');

        // Re-install dependencies
        $style->info('Reinstalling dependencies...');
        $process = new Process(
            ['composer', 'install', '--no-dev', '--optimize-autoloader', '--no-interaction'],
            $projectPath,
        );
        $process->setTimeout(300);
        $process->run();

        if ($process->isSuccessful()) {
            $style->success('Dependencies reinstalled');
        } else {
            $style->warning('Dependency install had issues: ' . $process->getErrorOutput());
        }

        // Rebuild caches
        $style->info('Rebuilding caches...');

        $cacheCommands = [
            ['php', 'lattice', 'config:cache'],
            ['php', 'lattice', 'route:cache'],
        ];

        foreach ($cacheCommands as $cmd) {
            $process = new Process($cmd, $projectPath);
            $process->setTimeout(30);
            $process->run();
        }

        $style->success('Caches rebuilt');

        // Restart queue workers
        $style->info('Restarting queue workers...');
        $process = new Process(
            ['php', 'lattice', 'queue:restart'],
            $projectPath,
        );
        $process->setTimeout(30);
        $process->run();

        $style->success('Queue workers restarted');

        // Get new HEAD
        $process = new Process(['git', 'rev-parse', '--short', 'HEAD'], $projectPath);
        $process->run();
        $newHead = $process->isSuccessful() ? trim($process->getOutput()) : 'unknown';

        $style->newLine();
        $style->header('Rollback Complete');
        $style->newLine();
        $style->keyValue('Previous HEAD', $currentHead);
        $style->keyValue('Current HEAD', $newHead);
        $style->newLine();
        $style->success('Rollback completed successfully');
        $style->newLine();

        return Command::SUCCESS;
    }
}
