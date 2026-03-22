<?php

declare(strict_types=1);

namespace Lattice\DevTools\Console\Illuminate;

use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Lists all registered routes in the application.
 *
 * Usage: lattice route:list
 */
final class RouteListCommand extends LatticeCommand
{
    /** @var array<int, array{method: string, uri: string, name: string|null, action: string, middleware: string[]}> */
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
     * @param array<int, array{method: string, uri: string, name: string|null, action: string, middleware: string[]}> $routes
     */
    public function setRoutes(array $routes): void
    {
        $this->routes = $routes;
    }

    protected function handle(InputInterface $input, OutputInterface $output): int
    {
        $routes = $this->routes;
        $methodFilter = $input->getOption('method');
        $pathFilter = $input->getOption('path');

        if ($methodFilter) {
            $routes = array_filter($routes, fn(array $r): bool => stripos($r['method'], $methodFilter) !== false);
        }

        if ($pathFilter) {
            $routes = array_filter($routes, fn(array $r): bool => str_contains($r['uri'], $pathFilter));
        }

        if ($input->getOption('json')) {
            $output->writeln((string) json_encode(array_values($routes), JSON_PRETTY_PRINT));
            return self::SUCCESS;
        }

        if (empty($routes)) {
            $output->writeln('<comment>No routes registered.</comment>');
            return self::SUCCESS;
        }

        $table = new Table($output);
        $table->setHeaders(['Method', 'URI', 'Name', 'Action', 'Middleware']);

        foreach ($routes as $route) {
            $table->addRow([
                $route['method'],
                $route['uri'],
                $route['name'] ?? '',
                $route['action'],
                implode(', ', $route['middleware'] ?? []),
            ]);
        }

        $table->render();

        return self::SUCCESS;
    }
}
