<?php

declare(strict_types=1);

namespace Lattice\Core\Console\Commands;

use Lattice\Core\Console\LatticeStyle;
use Lattice\Routing\Router;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class RouteCacheCommand extends Command
{
    public function __construct(
        private readonly Router $router,
        private readonly string $basePath,
    ) {
        parent::__construct('route:cache');
    }

    protected function configure(): void
    {
        $this->setDescription('Create a route cache file for faster route registration');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $style = new LatticeStyle($output);
        $style->banner();
        $style->header('Route Cache');
        $style->newLine();

        $routes = $this->router->getRoutes();

        $serialized = [];
        foreach ($routes as $route) {
            $serialized[] = [
                'httpMethod' => $route->httpMethod,
                'path' => $route->path,
                'controllerClass' => $route->controllerClass,
                'methodName' => $route->methodName,
                'name' => $route->name,
                'guards' => $route->guards,
                'interceptors' => $route->interceptors,
                'pipes' => $route->pipes,
                'filters' => $route->filters,
                'version' => $route->version,
            ];
        }

        $cacheDir = $this->basePath . '/bootstrap/cache';

        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }

        $cachePath = $cacheDir . '/routes.php';
        $content = '<?php return ' . var_export($serialized, true) . ';' . "\n";

        $result = file_put_contents($cachePath, $content);

        if ($result === false) {
            $style->error('Failed to write route cache file');
            $style->newLine();

            return Command::FAILURE;
        }

        $style->success('Routes cached successfully');
        $style->keyValue('Path', $cachePath);
        $style->keyValue('Routes', (string) count($routes));
        $style->newLine();

        return Command::SUCCESS;
    }
}
