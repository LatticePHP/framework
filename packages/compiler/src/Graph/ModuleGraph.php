<?php

declare(strict_types=1);

namespace Lattice\Compiler\Graph;

final class ModuleGraph
{
    /**
     * @param array<string, ModuleNode> $modules Keyed by class name
     * @param array<string> $topologicalOrder Boot order
     */
    public function __construct(
        private readonly array $modules,
        private readonly array $topologicalOrder,
    ) {}

    /**
     * @return array<string, ModuleNode>
     */
    public function getModules(): array
    {
        return $this->modules;
    }

    public function getModule(string $name): ModuleNode
    {
        if (!isset($this->modules[$name])) {
            throw new \InvalidArgumentException("Module '{$name}' not found in the graph.");
        }

        return $this->modules[$name];
    }

    /**
     * @return array<string> Module class names in topological (boot) order
     */
    public function getTopologicalOrder(): array
    {
        return $this->topologicalOrder;
    }

    /**
     * @return array<string> Class names exported by the given module
     */
    public function getExportsFor(string $module): array
    {
        return $this->getModule($module)->exports;
    }

    public function isGlobal(string $module): bool
    {
        return $this->getModule($module)->isGlobal;
    }
}
