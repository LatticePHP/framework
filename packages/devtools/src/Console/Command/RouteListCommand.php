<?php

declare(strict_types=1);

namespace Lattice\DevTools\Console\Command;

use Lattice\DevTools\Console\Command as BaseCommand;
use Lattice\DevTools\Console\Input;
use Lattice\DevTools\Console\Output;

final class RouteListCommand extends BaseCommand
{
    /** @var list<array{method: string, uri: string, action: string}> */
    private array $routes;

    /** @param list<array{method: string, uri: string, action: string}> $routes */
    public function __construct(array $routes = [])
    {
        $this->routes = $routes;
    }

    public function name(): string
    {
        return 'route:list';
    }

    public function description(): string
    {
        return 'List all registered routes';
    }

    public function handle(Input $input, Output $output): int
    {
        if ($this->routes === []) {
            $output->info('No routes registered.');
            return 0;
        }

        $rows = [];
        foreach ($this->routes as $route) {
            $rows[] = [$route['method'], $route['uri'], $route['action']];
        }

        $output->table(['Method', 'URI', 'Action'], $rows);

        return 0;
    }

    /** @param list<array{method: string, uri: string, action: string}> $routes */
    public function setRoutes(array $routes): void
    {
        $this->routes = $routes;
    }
}
