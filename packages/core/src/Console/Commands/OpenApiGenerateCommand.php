<?php

declare(strict_types=1);

namespace Lattice\Core\Console\Commands;

use Lattice\Core\Console\LatticeStyle;
use Lattice\OpenApi\OpenApiGenerator;
use Lattice\Routing\Router;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class OpenApiGenerateCommand extends Command
{
    public function __construct(
        private readonly Router $router,
        private readonly OpenApiGenerator $generator,
        private readonly string $basePath,
    ) {
        parent::__construct('openapi:generate');
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Generate OpenAPI 3.1 specification from route metadata')
            ->addOption('output', 'o', InputOption::VALUE_OPTIONAL, 'Output file path')
            ->addOption('format', 'f', InputOption::VALUE_OPTIONAL, 'Output format (json or yaml)', 'json')
            ->addOption('stdout', null, InputOption::VALUE_NONE, 'Output to stdout instead of file');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $style = new LatticeStyle($output);
        $format = (string) $input->getOption('format');
        $stdout = (bool) $input->getOption('stdout');

        if (!$stdout) {
            $style->banner();
            $style->header('OpenAPI Generator');
            $style->newLine();
        }

        $routes = $this->router->getRoutes();

        $routeData = [];
        foreach ($routes as $route) {
            $routeData[] = [
                'path' => $route->path,
                'method' => $route->httpMethod,
                'controller' => $route->controllerClass,
                'action' => $route->methodName,
            ];
        }

        $doc = $this->generator->generate($routeData);

        if ($format === 'yaml') {
            $content = $doc->toYaml();
        } else {
            $content = $doc->toJson();
        }

        if ($stdout) {
            $output->write($content);

            return Command::SUCCESS;
        }

        $outputPath = $input->getOption('output');

        if (!is_string($outputPath) || $outputPath === '') {
            $extension = $format === 'yaml' ? 'yaml' : 'json';
            $outputPath = $this->basePath . '/openapi.' . $extension;
        }

        $dir = dirname($outputPath);

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $result = file_put_contents($outputPath, $content);

        if ($result === false) {
            $style->error('Failed to write OpenAPI specification');
            $style->newLine();

            return Command::FAILURE;
        }

        $style->success('OpenAPI specification generated successfully');
        $style->keyValue('Format', strtoupper($format));
        $style->keyValue('Path', $outputPath);
        $style->keyValue('Routes', (string) count($routes));
        $style->newLine();

        return Command::SUCCESS;
    }
}
