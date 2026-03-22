<?php

declare(strict_types=1);

namespace Lattice\Core\Console\Commands;

use Lattice\Core\Console\LatticeStyle;
use Lattice\Database\Illuminate\IlluminateDatabaseManager;
use Lattice\Database\ModuleMigrationDiscoverer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class DbSeedCommand extends Command
{
    public function __construct(
        private readonly IlluminateDatabaseManager $db,
        private readonly ModuleMigrationDiscoverer $discoverer,
        /** @var list<string> */
        private readonly array $moduleClasses = [],
    ) {
        parent::__construct('db:seed');
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Run database seeders')
            ->addArgument('class', InputArgument::OPTIONAL, 'The seeder class to run')
            ->addOption('path', null, InputOption::VALUE_OPTIONAL, 'The path to seeder files (overrides module discovery)')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Force in production');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $class = $input->getArgument('class');
        $path = $input->getOption('path');
        $style = new LatticeStyle($output);

        $style->banner();
        $style->header('Database Seeding');
        $style->newLine();

        // If a specific class was given, run it directly
        if ($class !== null && is_string($class) && class_exists($class)) {
            $seeder = new $class();

            if (method_exists($seeder, 'run')) {
                $seeder->run();
                $style->success("Ran seeder: {$class}");
                $style->newLine();
                return Command::SUCCESS;
            }

            $style->error("Seeder class {$class} does not have a run() method");
            return Command::FAILURE;
        }

        // If explicit path provided
        if ($path !== null && is_string($path)) {
            if (!is_dir($path)) {
                $style->warning("Seeder directory not found: {$path}");
                $style->info("Create seeders in <fg=white>{$path}</> to get started");
                return Command::FAILURE;
            }

            return $this->runSeedersFromPath($path, $style);
        }

        // Module discovery mode
        $seederFiles = $this->discoverer->discoverSeeders($this->moduleClasses);

        if ($seederFiles === []) {
            $style->info('No seeder files found across modules');
            return Command::SUCCESS;
        }

        $bar = $style->progressBar(count($seederFiles));
        $bar->start();

        $seeded = 0;
        foreach ($seederFiles as $file) {
            $name = basename($file, '.php');
            $bar->setMessage("Seeding: {$name}");

            require_once $file;

            $bar->advance();
            $seeded++;
        }

        $bar->setMessage('');
        $bar->finish();
        $style->newLine(2);

        $style->success("Ran {$seeded} seeders");
        $style->newLine();

        return Command::SUCCESS;
    }

    private function runSeedersFromPath(string $path, LatticeStyle $style): int
    {
        $files = glob($path . '/*.php');

        if ($files === false || $files === []) {
            $style->info('No seeder files found');
            return Command::SUCCESS;
        }

        $bar = $style->progressBar(count($files));
        $bar->start();

        $seeded = 0;
        foreach ($files as $file) {
            $name = basename($file, '.php');
            $bar->setMessage("Seeding: {$name}");

            require_once $file;

            $bar->advance();
            $seeded++;
        }

        $bar->setMessage('');
        $bar->finish();
        $style->newLine(2);

        $style->success("Ran {$seeded} seeders");
        $style->newLine();

        return Command::SUCCESS;
    }
}
