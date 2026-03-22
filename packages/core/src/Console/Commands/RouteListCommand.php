<?php

declare(strict_types=1);

namespace Lattice\Core\Console\Commands;

use Lattice\Core\Console\LatticeStyle;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class RouteListCommand extends Command
{
    /** @var array<int, array{method: string, uri: string, name: string|null, action: string, middleware: string[], guards: string[]}> */
    private array $routes = [];

    public function __construct()
    {
        parent::__construct('route:list');
    }

    protected function configure(): void
    {
        $this
            ->setDescription('List all registered routes')
            ->addOption('method', null, InputOption::VALUE_OPTIONAL, 'Filter by HTTP method')
            ->addOption('path', null, InputOption::VALUE_OPTIONAL, 'Filter by path pattern')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Output as JSON');
    }

    /**
     * @param array<int, array{method: string, uri: string, name: string|null, action: string, middleware?: string[], guards?: string[]}> $routes
     */
    public function setRoutes(array $routes): void
    {
        $this->routes = array_map(fn(array $r): array => [
            'method' => $r['method'],
            'uri' => $r['uri'],
            'name' => $r['name'],
            'action' => $r['action'],
            'middleware' => $r['middleware'] ?? [],
            'guards' => $r['guards'] ?? [],
        ], $routes);
    }

    /**
     * @return array<int, array{method: string, uri: string, name: string|null, action: string, middleware: string[], guards: string[]}>
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $routes = $this->routes;
        $methodFilter = $input->getOption('method');
        $pathFilter = $input->getOption('path');

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

        if ($input->getOption('json')) {
            $output->writeln((string) json_encode(array_values($routes), JSON_PRETTY_PRINT));
            return Command::SUCCESS;
        }

        $style = new LatticeStyle($output);
        $style->banner();
        $style->header('Registered Routes');
        $style->newLine();

        if ($routes === []) {
            $style->info('No routes registered');
            $style->newLine();
            return Command::SUCCESS;
        }

        $rows = [];
        foreach ($routes as $route) {
            $methodColors = [
                'GET' => '<fg=green>GET</>',
                'POST' => '<fg=blue>POST</>',
                'PUT' => '<fg=yellow>PUT</>',
                'PATCH' => '<fg=yellow>PATCH</>',
                'DELETE' => '<fg=red>DELETE</>',
                'HEAD' => '<fg=gray>HEAD</>',
                'OPTIONS' => '<fg=gray>OPTIONS</>',
            ];

            $method = $methodColors[strtoupper($route['method'])] ?? $route['method'];

            $rows[] = [
                $method,
                $route['uri'],
                $route['name'] ?? '',
                $route['action'],
                implode(', ', $route['guards']),
            ];
        }

        $style->table(
            ['Method', 'URI', 'Name', 'Action', 'Guards'],
            $rows,
        );

        $style->newLine();
        $style->info('Total routes: ' . count($routes));
        $style->newLine();

        return Command::SUCCESS;
    }
}
