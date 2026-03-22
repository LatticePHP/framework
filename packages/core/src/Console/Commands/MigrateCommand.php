<?php

declare(strict_types=1);

namespace Lattice\Core\Console\Commands;

use Lattice\Core\Console\LatticeStyle;
use Lattice\Database\Illuminate\IlluminateDatabaseManager;
use Lattice\Database\Illuminate\IlluminateMigrationRunner;
use Lattice\Database\ModuleMigrationDiscoverer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class MigrateCommand extends Command
{
    public function __construct(
        private readonly IlluminateDatabaseManager $db,
        private readonly ModuleMigrationDiscoverer $discoverer,
        /** @var list<string> */
        private readonly array $moduleClasses = [],
    ) {
        parent::__construct('migrate');
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Run pending database migrations')
            ->addOption('path', null, InputOption::VALUE_OPTIONAL, 'The path to migration files (overrides module discovery)')
            ->addOption('pretend', null, InputOption::VALUE_NONE, 'Dump the SQL queries that would be run')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Force in production');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $path = $input->getOption('path');
        $pretend = (bool) $input->getOption('pretend');
        $style = new LatticeStyle($output);

        $style->banner();
        $style->header('Running Migrations');
        $style->newLine();

        $runner = new IlluminateMigrationRunner($this->db);

        if ($path !== null && is_string($path)) {
            // Explicit path mode — run from a single directory
            if (!is_dir($path)) {
                $style->warning("Migration directory not found: {$path}");
                return Command::FAILURE;
            }

            if ($pretend) {
                $files = glob($path . '/*.php');
                $count = $files === false ? 0 : count($files);
                $style->info("Pretend mode: {$count} migration files found in {$path}");
                return Command::SUCCESS;
            }

            $runner->run($path);
            $style->success("Ran migrations from {$path}");
            $style->newLine();

            return Command::SUCCESS;
        }

        // Module discovery mode
        $migrationFiles = $this->discoverer->discover($this->moduleClasses);

        if ($migrationFiles === []) {
            $style->info('No migration files found across modules');
            return Command::SUCCESS;
        }

        if ($pretend) {
            $style->info("Pretend mode: " . count($migrationFiles) . " migration files found across modules");
            foreach ($migrationFiles as $file) {
                $style->keyValue('  File', basename($file));
            }
            return Command::SUCCESS;
        }

        // Group files by directory and run each group
        $directories = $this->groupByDirectory($migrationFiles);

        $bar = $style->progressBar(count($migrationFiles));
        $bar->start();

        $totalMigrated = 0;
        foreach ($directories as $dir => $files) {
            $runner->run($dir);

            foreach ($files as $file) {
                $name = basename($file, '.php');
                $bar->setMessage("Migrated: {$name}");
                $bar->advance();
                $totalMigrated++;
            }
        }

        $bar->setMessage('');
        $bar->finish();
        $style->newLine(2);

        $style->success("Ran {$totalMigrated} migrations");
        $style->newLine();

        return Command::SUCCESS;
    }

    /**
     * Group migration file paths by their parent directory.
     *
     * @param list<string> $files
     * @return array<string, list<string>>
     */
    private function groupByDirectory(array $files): array
    {
        $groups = [];

        foreach ($files as $file) {
            $dir = dirname($file);
            $groups[$dir][] = $file;
        }

        return $groups;
    }
}
