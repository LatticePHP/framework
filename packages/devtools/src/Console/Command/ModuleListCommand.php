<?php

declare(strict_types=1);

namespace Lattice\DevTools\Console\Command;

use Lattice\DevTools\Console\Command as BaseCommand;
use Lattice\DevTools\Console\Input;
use Lattice\DevTools\Console\Output;

final class ModuleListCommand extends BaseCommand
{
    /** @var list<array{name: string, status: string}> */
    private array $modules;

    /** @param list<array{name: string, status: string}> $modules */
    public function __construct(array $modules = [])
    {
        $this->modules = $modules;
    }

    public function name(): string
    {
        return 'module:list';
    }

    public function description(): string
    {
        return 'List all registered modules';
    }

    public function handle(Input $input, Output $output): int
    {
        if ($this->modules === []) {
            $output->info('No modules registered.');
            return 0;
        }

        $rows = [];
        foreach ($this->modules as $module) {
            $rows[] = [$module['name'], $module['status']];
        }

        $output->table(['Module', 'Status'], $rows);

        return 0;
    }

    /** @param list<array{name: string, status: string}> $modules */
    public function setModules(array $modules): void
    {
        $this->modules = $modules;
    }
}
