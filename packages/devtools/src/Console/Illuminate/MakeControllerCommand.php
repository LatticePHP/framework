<?php

declare(strict_types=1);

namespace Lattice\DevTools\Console\Illuminate;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Scaffolds a new controller within a LatticePHP module.
 *
 * Usage: lattice make:controller Users/UserController
 */
final class MakeControllerCommand extends LatticeCommand
{
    public function __construct()
    {
        parent::__construct('make:controller');
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Create a new controller')
            ->addArgument('name', InputArgument::REQUIRED, 'The controller name (e.g., Users/UserController)')
            ->addOption('module', 'm', InputOption::VALUE_OPTIONAL, 'The module to create the controller in')
            ->addOption('crud', null, InputOption::VALUE_NONE, 'Generate CRUD action methods');
    }

    protected function handle(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getArgument('name');
        $module = $input->getOption('module');
        $crud = $input->getOption('crud');

        $parts = explode('/', $name);
        $className = array_pop($parts);
        $subNamespace = implode('\\', $parts);

        $namespace = $module
            ? "App\\Modules\\{$module}\\Controllers" . ($subNamespace ? "\\{$subNamespace}" : '')
            : "App\\Controllers" . ($subNamespace ? "\\{$subNamespace}" : '');

        $basePath = $module
            ? "src/Modules/{$module}/Controllers"
            : "src/Controllers";

        if ($parts) {
            $basePath .= '/' . implode('/', $parts);
        }

        if (!is_dir($basePath)) {
            mkdir($basePath, 0755, true);
        }

        $methods = '';
        if ($crud) {
            $methods = <<<'PHP'

                public function index(): array
                {
                    return [];
                }

                public function show(string $id): array
                {
                    return [];
                }

                public function store(): array
                {
                    return [];
                }

                public function update(string $id): array
                {
                    return [];
                }

                public function destroy(string $id): void
                {
                }
            PHP;
        }

        $content = <<<PHP
        <?php

        declare(strict_types=1);

        namespace {$namespace};

        use Lattice\\Routing\\Attributes\\Controller;

        #[Controller]
        final class {$className}
        {{$methods}
        }
        PHP;

        $filePath = $basePath . '/' . $className . '.php';
        file_put_contents($filePath, $content);

        $output->writeln("<info>Controller '{$className}' created at {$filePath}</info>");

        return self::SUCCESS;
    }
}
