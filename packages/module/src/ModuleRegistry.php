<?php

declare(strict_types=1);

namespace Lattice\Module;

use Lattice\Contracts\Module\ModuleDefinitionInterface;
use Lattice\Module\Exception\CircularDependencyException;
use Lattice\Module\Exception\ModuleNotFoundException;

final class ModuleRegistry
{
    /** @var array<string, ModuleDefinitionInterface> */
    private array $modules = [];

    public function register(string $className, ModuleDefinitionInterface $definition): void
    {
        $this->modules[$className] = $definition;
    }

    public function get(string $className): ModuleDefinitionInterface
    {
        if (!isset($this->modules[$className])) {
            throw ModuleNotFoundException::forClass($className);
        }

        return $this->modules[$className];
    }

    public function has(string $className): bool
    {
        return isset($this->modules[$className]);
    }

    /** @return array<string, ModuleDefinitionInterface> */
    public function all(): array
    {
        return $this->modules;
    }

    /**
     * Returns module class names in topological boot order (dependencies first).
     *
     * @return list<string>
     * @throws CircularDependencyException
     */
    public function getBootOrder(): array
    {
        $sorted = [];
        $visited = [];
        $visiting = [];

        foreach (array_keys($this->modules) as $module) {
            if (!isset($visited[$module])) {
                $this->topologicalSort($module, $visited, $visiting, $sorted);
            }
        }

        return $sorted;
    }

    /**
     * @param array<string, true> $visited
     * @param array<string, true> $visiting
     * @param list<string> $sorted
     */
    private function topologicalSort(
        string $module,
        array &$visited,
        array &$visiting,
        array &$sorted,
    ): void {
        if (isset($visiting[$module])) {
            throw CircularDependencyException::forModules([...\array_keys($visiting), $module]);
        }

        if (isset($visited[$module])) {
            return;
        }

        $visiting[$module] = true;

        if (isset($this->modules[$module])) {
            foreach ($this->modules[$module]->getImports() as $import) {
                $this->topologicalSort($import, $visited, $visiting, $sorted);
            }
        }

        unset($visiting[$module]);
        $visited[$module] = true;
        $sorted[] = $module;
    }
}
