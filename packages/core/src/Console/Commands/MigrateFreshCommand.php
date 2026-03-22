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

final class MigrateFreshCommand extends Command
{
    public function __construct(
        private readonly IlluminateDatabaseManager $db,
        private readonly ModuleMigrationDiscoverer $discoverer,
        /** @var list<string> */
        private readonly array $moduleClasses = [],
    ) {
        parent::__construct('migrate:fresh');
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Drop all tables and re-run all migrations')
            ->addOption('path', null, InputOption::VALUE_OPTIONAL, 'The path to migration files (overrides module discovery)')
            ->addOption('seed', null, InputOption::VALUE_NONE, 'Run seeders after migration')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Force in production');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $path = $input->getOption('path');
        $seed = (bool) $input->getOption('seed');
        $style = new LatticeStyle($output);

        $style->banner();
        $style->header('Fresh Migration');
        $style->newLine();

        // Drop all tables
        $style->warning('Dropping all tables...');
        $this->dropAllTables();
        $style->success('All tables dropped');
        $style->newLine();

        $runner = new IlluminateMigrationRunner($this->db);

        if ($path !== null && is_string($path)) {
            if (!is_dir($path)) {
                $style->warning("Migration directory not found: {$path}");
                return Command::FAILURE;
            }

            $style->info('Running migrations...');
            $runner->run($path);
            $style->success("Ran migrations from {$path}");
        } else {
            // Module discovery mode
            $migrationFiles = $this->discoverer->discover($this->moduleClasses);

            if ($migrationFiles === []) {
                $style->info('No migration files found across modules');
                return Command::SUCCESS;
            }

            $style->info('Running migrations...');
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

            $style->success("Ran {$totalMigrated} migrations from scratch");
        }

        if ($seed) {
            $style->newLine();
            $style->info('Running seeders...');

            $seederFiles = $this->discoverer->discoverSeeders($this->moduleClasses);

            if ($seederFiles === []) {
                $style->info('No seeders found');
            } else {
                foreach ($seederFiles as $file) {
                    require_once $file;
                }
                $style->success('Database seeded');
            }
        }

        $style->newLine();

        return Command::SUCCESS;
    }

    private function dropAllTables(): void
    {
        $schema = $this->db->schema();
        $tables = $schema->getTables();

        // Disable foreign key checks for clean drops
        $connection = $this->db->connection();
        $driver = $connection->getDriverName();

        if ($driver === 'mysql') {
            $connection->statement('SET FOREIGN_KEY_CHECKS=0');
        } elseif ($driver === 'sqlite') {
            $connection->statement('PRAGMA foreign_keys = OFF');
        } elseif ($driver === 'pgsql') {
            // PostgreSQL handles cascading drops differently
        }

        foreach ($tables as $table) {
            $tableName = is_array($table) ? ($table['name'] ?? $table[0] ?? '') : (string) $table;
            if ($tableName !== '') {
                $schema->dropIfExists($tableName);
            }
        }

        if ($driver === 'mysql') {
            $connection->statement('SET FOREIGN_KEY_CHECKS=1');
        } elseif ($driver === 'sqlite') {
            $connection->statement('PRAGMA foreign_keys = ON');
        }
    }

    /**
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
