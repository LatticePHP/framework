<?php

declare(strict_types=1);

namespace Lattice\Core\Console\Commands;

use Lattice\Core\Console\LatticeStyle;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class TestCommand extends Command
{
    public function __construct()
    {
        parent::__construct('test');
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Run the application test suite via PHPUnit')
            ->addArgument('filter', InputArgument::OPTIONAL, 'Filter which tests to run')
            ->addOption('testsuite', null, InputOption::VALUE_OPTIONAL, 'The test suite to run')
            ->addOption('coverage', null, InputOption::VALUE_NONE, 'Generate code coverage report')
            ->addOption('parallel', null, InputOption::VALUE_NONE, 'Run tests in parallel');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $filter = $input->getArgument('filter');
        $testsuite = $input->getOption('testsuite');
        $coverage = (bool) $input->getOption('coverage');
        $parallel = (bool) $input->getOption('parallel');
        $style = new LatticeStyle($output);

        $style->banner();
        $style->header('Running Tests');
        $style->newLine();

        $phpunit = $this->findPhpUnit();
        if ($phpunit === null) {
            $style->error('PHPUnit not found. Run <fg=white>composer require --dev phpunit/phpunit</>');
            return Command::FAILURE;
        }

        $args = [$phpunit, '--colors=always'];

        if (is_string($filter) && $filter !== '') {
            $args[] = '--filter=' . escapeshellarg($filter);
            $style->info("Filter: {$filter}");
        }

        if (is_string($testsuite) && $testsuite !== '') {
            $args[] = '--testsuite=' . escapeshellarg($testsuite);
            $style->info("Suite: {$testsuite}");
        }

        if ($coverage) {
            $args[] = '--coverage-text';
            $style->info('Coverage enabled');
        }

        if ($parallel) {
            $style->info('Parallel execution requested');
        }

        $style->newLine();

        $command = implode(' ', $args);
        $result = 0;
        passthru($command, $result);

        $style->newLine();

        if ($result === 0) {
            $style->success('All tests passed');
        } else {
            $style->error('Some tests failed');
        }

        $style->newLine();

        return $result === 0 ? Command::SUCCESS : Command::FAILURE;
    }

    private function findPhpUnit(): ?string
    {
        $paths = [
            getcwd() . '/vendor/bin/phpunit',
            getcwd() . '/vendor/bin/phpunit.bat',
        ];

        foreach ($paths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        // Try global
        $which = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' ? 'where' : 'which';
        $result = shell_exec("{$which} phpunit 2>/dev/null");

        if ($result !== null && trim($result) !== '') {
            return trim($result);
        }

        return null;
    }
}
