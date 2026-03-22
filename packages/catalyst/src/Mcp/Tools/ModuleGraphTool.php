<?php

declare(strict_types=1);

namespace Lattice\Catalyst\Mcp\Tools;

use Lattice\Catalyst\Mcp\McpToolInterface;

final class ModuleGraphTool implements McpToolInterface
{
    /**
     * @param array<string, array{imports: string[], exports: string[], providers: string[], controllers: string[]}> $modules
     */
    public function __construct(
        private readonly array $modules = [],
    ) {}

    public function getName(): string
    {
        return 'module_graph';
    }

    public function getDescription(): string
    {
        return 'Returns the module dependency tree showing all registered modules and their dependencies';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'module' => [
                    'type' => 'string',
                    'description' => 'Filter to a specific module name',
                ],
            ],
            'required' => [],
        ];
    }

    public function execute(array $arguments): array
    {
        $moduleFilter = $arguments['module'] ?? null;

        if (is_string($moduleFilter) && $moduleFilter !== '') {
            $filtered = [];

            foreach ($this->modules as $name => $info) {
                if (str_contains($name, $moduleFilter)) {
                    $filtered[$name] = $info;
                }
            }

            return [
                'total' => count($filtered),
                'modules' => $filtered,
            ];
        }

        return [
            'total' => count($this->modules),
            'modules' => $this->modules,
        ];
    }
}
