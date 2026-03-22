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

final class MigrateRollbackCommand extends Command
{
    public function __construct(
        private readonly IlluminateDatabaseManager $db,
        private readonly ModuleMigrationDiscoverer $discoverer,
        /** @var list<string> */
        private readonly array $moduleClasses = [],
    ) {
        parent::__construct('migrate:rollback');
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Rollback the last database migration batch')
            ->addOption('step', null, InputOption::VALUE_OPTIONAL, 'Number of batches to rollback', '1')
            ->addOption('path', null, InputOption::VALUE_OPTIONAL, 'The path to migration files (overrides module discovery)')
            ->addOption('pretend', null, InputOption::VALUE_NONE, 'Dump the SQL queries that would be run');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $step = (int) $input->getOption('step');
        $path = $input->getOption('path');
        $pretend = (bool) $input->getOption('pretend');
        $style = new LatticeStyle($output);

        $style->banner();
        $style->header('Rolling Back Migrations');
        $style->newLine();

        $runner = new IlluminateMigrationRunner($this->db);

        if ($path !== null && is_string($path)) {
            if (!is_dir($path)) {
                $style->warning("Migration directory not found: {$path}");
                return Command::FAILURE;
            }

            if ($pretend) {
                $style->info("Pretend mode: would rollback last batch from {$path}");
                return Command::SUCCESS;
            }

            for ($i = 0; $i < $step; $i++) {
                $runner->rollback($path);
            }

            $style->success("Rolled back {$step} batch(es) from {$path}");
            $style->newLine();

            return Command::SUCCESS;
        }

        // Module discovery mode — rollback from all module migration directories
        $migrationFiles = $this->discoverer->discover($this->moduleClasses);

        if ($migrationFiles === []) {
            $style->info('No migration files found across modules');
            return Command::SUCCESS;
        }

        if ($pretend) {
            $style->info("Pretend mode: would rollback last {$step} batch(es) across module migration directories");
            return Command::SUCCESS;
        }

        // Get unique directories (in reverse order for rollback)
        $directories = array_unique(array_map('dirname', $migrationFiles));
        $directories = array_reverse($directories);

        for ($i = 0; $i < $step; $i++) {
            foreach ($directories as $dir) {
                $runner->rollback($dir);
            }
        }

        $style->success("Rolled back {$step} batch(es)");
        $style->newLine();

        return Command::SUCCESS;
    }
}
