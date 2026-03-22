<?php

declare(strict_types=1);

namespace Lattice\Compiler\Graph;

use Lattice\Compiler\Discovery\ModuleMetadata;
use Lattice\Compiler\Exceptions\CircularDependencyException;
use Lattice\Compiler\Exceptions\ExportViolationException;
use Lattice\Compiler\Exceptions\UnresolvedImportException;

final class ModuleGraphBuilder
{
    /** @var array<string, ModuleMetadata> */
    private array $modules = [];

    public function addModule(string $className, ModuleMetadata $metadata): void
    {
        $this->modules[$className] = $metadata;
    }

    /**
     * Build the module graph. Validates imports, exports, and detects cycles.
     *
     * @throws UnresolvedImportException
     * @throws ExportViolationException
     * @throws CircularDependencyException
     */
    public function build(): ModuleGraph
    {
        $this->validateImports();
        $this->validateExports();

        $nodes = $this->buildNodes();
        $order = $this->topologicalSort();

        return new ModuleGraph($nodes, $order);
    }

    private function validateImports(): void
    {
        foreach ($this->modules as $className => $metadata) {
            foreach ($metadata->imports as $import) {
                if (!isset($this->modules[$import])) {
                    throw new UnresolvedImportException($className, $import);
                }
            }
        }
    }

    private function validateExports(): void
    {
        foreach ($this->modules as $className => $metadata) {
            foreach ($metadata->exports as $export) {
                if (!in_array($export, $metadata->providers, true)) {
                    throw new ExportViolationException($className, $export);
                }
            }
        }
    }

    /**
     * @return array<string, ModuleNode>
     */
    private function buildNodes(): array
    {
        $nodes = [];

        foreach ($this->modules as $className => $metadata) {
            $nodes[$className] = new ModuleNode(
                className: $className,
                imports: $metadata->imports,
                providers: $metadata->providers,
                controllers: $metadata->controllers,
                exports: $metadata->exports,
                isGlobal: $metadata->isGlobal,
            );
        }

        return $nodes;
    }

    /**
     * Kahn's algorithm for topological sorting with cycle detection.
     *
     * @return array<string>
     * @throws CircularDependencyException
     */
    private function topologicalSort(): array
    {
        // Build adjacency list and in-degree count
        $inDegree = [];
        $adjacency = [];

        foreach ($this->modules as $className => $metadata) {
            $inDegree[$className] ??= 0;
            $adjacency[$className] ??= [];

            foreach ($metadata->imports as $import) {
                $adjacency[$import][] = $className;
                $inDegree[$className] = ($inDegree[$className] ?? 0) + 1;
                $inDegree[$import] ??= 0;
            }
        }

        // Enqueue nodes with zero in-degree
        $queue = [];
        foreach ($inDegree as $node => $degree) {
            if ($degree === 0) {
                $queue[] = $node;
            }
        }

        $sorted = [];

        while (!empty($queue)) {
            $current = array_shift($queue);
            $sorted[] = $current;

            foreach ($adjacency[$current] as $neighbor) {
                $inDegree[$neighbor]--;

                if ($inDegree[$neighbor] === 0) {
                    $queue[] = $neighbor;
                }
            }
        }

        // If not all nodes are in sorted result, there is a cycle
        if (count($sorted) !== count($this->modules)) {
            $cycle = $this->findCycle();
            throw new CircularDependencyException($cycle);
        }

        return $sorted;
    }

    /**
     * Find one cycle in the graph using DFS for error reporting.
     *
     * @return array<string>
     */
    private function findCycle(): array
    {
        $visited = [];
        $stack = [];

        foreach (array_keys($this->modules) as $node) {
            $cycle = $this->dfs($node, $visited, $stack);

            if ($cycle !== null) {
                return $cycle;
            }
        }

        return ['unknown cycle'];
    }

    private function dfs(string $node, array &$visited, array &$stack): ?array
    {
        if (isset($stack[$node])) {
            // Found cycle - build cycle path
            $cycle = [$node];
            return [...$cycle, $node];
        }

        if (isset($visited[$node])) {
            return null;
        }

        $stack[$node] = true;

        foreach ($this->modules[$node]->imports as $import) {
            if (!isset($this->modules[$import])) {
                continue;
            }

            $result = $this->dfs($import, $visited, $stack);

            if ($result !== null) {
                // If we haven't closed the cycle yet, prepend current node
                if ($result[0] !== $result[count($result) - 1] || count($result) < 2) {
                    array_unshift($result, $node);
                }
                return $result;
            }
        }

        unset($stack[$node]);
        $visited[$node] = true;

        return null;
    }
}
