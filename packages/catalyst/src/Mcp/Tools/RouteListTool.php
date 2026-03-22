<?php

declare(strict_types=1);

namespace Lattice\Catalyst\Mcp\Tools;

use Lattice\Catalyst\Mcp\McpToolInterface;

final class RouteListTool implements McpToolInterface
{
    /**
     * @param array<int, array{method: string, uri: string, name: string|null, action: string, middleware: string[], guards: string[]}> $routes
     */
    public function __construct(
        private readonly array $routes = [],
    ) {}

    public function getName(): string
    {
        return 'route_list';
    }

    public function getDescription(): string
    {
        return 'Returns all registered routes with method, path, controller/handler, middleware stack, and route name';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'method' => [
                    'type' => 'string',
                    'description' => 'Filter by HTTP method (GET, POST, PUT, DELETE, etc.)',
                ],
                'path' => [
                    'type' => 'string',
                    'description' => 'Filter by path substring',
                ],
            ],
            'required' => [],
        ];
    }

    public function execute(array $arguments): array
    {
        $routes = $this->routes;

        $methodFilter = $arguments['method'] ?? null;
        $pathFilter = $arguments['path'] ?? null;

        if (is_string($methodFilter) && $methodFilter !== '') {
            $routes = array_filter(
                $routes,
                fn(array $r): bool => stripos($r['method'], $methodFilter) !== false,
            );
        }

        if (is_string($pathFilter) && $pathFilter !== '') {
            $routes = array_filter(
                $routes,
                fn(array $r): bool => str_contains($r['uri'], $pathFilter),
            );
        }

        return [
            'total' => count($routes),
            'routes' => array_values($routes),
        ];
    }
}
