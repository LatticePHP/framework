<?php

declare(strict_types=1);

namespace Lattice\Core\Console\Commands;

use Lattice\Core\Console\LatticeStyle;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class ModuleListCommand extends Command
{
    /** @var array<string, array{imports: string[], exports: string[], providers: string[], controllers: string[]}> */
    private array $modules = [];

    public function __construct()
    {
        parent::__construct('module:list');
    }

    protected function configure(): void
    {
        $this
            ->setDescription('List all registered modules and their dependencies')
            ->addOption('tree', 't', InputOption::VALUE_NONE, 'Display as dependency tree')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Output as JSON');
    }

    /**
     * @param array<string, array{imports?: string[], exports?: string[], providers?: string[], controllers?: string[]}> $modules
     */
    public function setModules(array $modules): void
    {
        $this->modules = array_map(fn(array $m): array => [
            'imports' => $m['imports'] ?? [],
            'exports' => $m['exports'] ?? [],
            'providers' => $m['providers'] ?? [],
            'controllers' => $m['controllers'] ?? [],
        ], $modules);
    }

    /**
     * @return array<string, array{imports: string[], exports: string[], providers: string[], controllers: string[]}>
     */
    public function getModules(): array
    {
        return $this->modules;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($input->getOption('json')) {
            $output->writeln((string) json_encode($this->modules, JSON_PRETTY_PRINT));
            return Command::SUCCESS;
        }

        $style = new LatticeStyle($output);
        $style->banner();
        $style->header('Registered Modules');
        $style->newLine();

        if ($this->modules === []) {
            $style->info('No modules registered');
            $style->newLine();
            return Command::SUCCESS;
        }

        if ($input->getOption('tree')) {
            $this->renderTree($style);
        } else {
            $this->renderTable($style);
        }

        $style->newLine();
        $style->info('Total modules: ' . count($this->modules));
        $style->newLine();

        return Command::SUCCESS;
    }

    private function renderTree(LatticeStyle $style): void
    {
        $tree = [];
        foreach ($this->modules as $name => $info) {
            $children = [];

            if ($info['imports'] !== []) {
                $children['Imports'] = array_map(
                    fn(string $i): string => "<fg=gray>{$this->shortName($i)}</>",
                    $info['imports'],
                );
            }

            if ($info['controllers'] !== []) {
                $children['Controllers'] = array_map(
                    fn(string $c): string => "<fg=yellow>{$this->shortName($c)}</>",
                    $info['controllers'],
                );
            }

            if ($info['providers'] !== []) {
                $children['Providers'] = array_map(
                    fn(string $p): string => "<fg=green>{$this->shortName($p)}</>",
                    $info['providers'],
                );
            }

            if ($info['exports'] !== []) {
                $children['Exports'] = array_map(
                    fn(string $e): string => "<fg=blue>{$this->shortName($e)}</>",
                    $info['exports'],
                );
            }

            $tree[$this->shortName($name)] = $children !== [] ? $children : ['<fg=gray>(empty)</>'];
        }

        $style->tree($tree);
    }

    private function renderTable(LatticeStyle $style): void
    {
        $rows = [];
        foreach ($this->modules as $name => $info) {
            $rows[] = [
                "<fg=cyan>{$this->shortName($name)}</>",
                (string) count($info['imports']),
                (string) count($info['controllers']),
                (string) count($info['providers']),
                (string) count($info['exports']),
            ];
        }

        $style->table(
            ['Module', 'Imports', 'Controllers', 'Providers', 'Exports'],
            $rows,
        );
    }

    private function shortName(string $fqcn): string
    {
        $parts = explode('\\', $fqcn);
        return end($parts);
    }
}
