<?php

declare(strict_types=1);

namespace Lattice\Core\Console\Commands;

use Lattice\Core\Console\LatticeStyle;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class MakeControllerCommand extends Command
{
    public function __construct()
    {
        parent::__construct('make:controller');
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Create a new controller with route attributes')
            ->addArgument('name', InputArgument::REQUIRED, 'The controller name (e.g., User or Users/UserController)')
            ->addOption('module', 'm', InputOption::VALUE_OPTIONAL, 'The module to create the controller in')
            ->addOption('crud', null, InputOption::VALUE_NONE, 'Generate CRUD action methods');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = (string) $input->getArgument('name');
        $module = $input->getOption('module');
        $crud = (bool) $input->getOption('crud');
        $style = new LatticeStyle($output);

        $style->banner();

        $parts = explode('/', $name);
        $className = array_pop($parts);

        if (!str_ends_with($className, 'Controller')) {
            $className .= 'Controller';
        }

        $subNamespace = implode('\\', $parts);
        $namespace = $module
            ? "App\\Modules\\{$module}\\Controllers" . ($subNamespace ? "\\{$subNamespace}" : '')
            : "App\\Controllers" . ($subNamespace ? "\\{$subNamespace}" : '');

        $basePath = $module
            ? "src/Modules/{$module}/Controllers"
            : "src/Controllers";

        if ($parts !== []) {
            $basePath .= '/' . implode('/', $parts);
        }

        if (!is_dir($basePath)) {
            mkdir($basePath, 0755, true);
        }

        $resourceName = lcfirst(str_replace('Controller', '', $className));
        $routePrefix = '/' . $this->pluralize($resourceName);

        $methods = '';
        $useStatements = "use Lattice\\Routing\\Attributes\\Controller;\n";

        if ($crud) {
            $useStatements .= "use Lattice\\Routing\\Attributes\\Delete;\n";
            $useStatements .= "use Lattice\\Routing\\Attributes\\Get;\n";
            $useStatements .= "use Lattice\\Routing\\Attributes\\Post;\n";
            $useStatements .= "use Lattice\\Routing\\Attributes\\Put;\n";

            $methods = <<<'PHP'

                #[Get('/')]
                public function index(): array
                {
                    return ['data' => []];
                }

                #[Get('/{id}')]
                public function show(string $id): array
                {
                    return ['data' => ['id' => $id]];
                }

                #[Post('/')]
                public function create(array $body): array
                {
                    return ['data' => $body, 'status' => 'created'];
                }

                #[Put('/{id}')]
                public function update(string $id, array $body): array
                {
                    return ['data' => array_merge(['id' => $id], $body)];
                }

                #[Delete('/{id}')]
                public function destroy(string $id): array
                {
                    return ['status' => 'deleted', 'id' => $id];
                }
            PHP;
        }

        $content = <<<PHP
            <?php

            declare(strict_types=1);

            namespace {$namespace};

            {$useStatements}
            #[Controller('{$routePrefix}')]
            final class {$className}
            {{$methods}
            }
            PHP;

        $filePath = $basePath . '/' . $className . '.php';
        file_put_contents($filePath, $content);

        $style->success("Controller <fg=white>{$className}</> created at <fg=gray>{$filePath}</>");

        if ($crud) {
            $style->info("CRUD methods generated: index, show, create, update, destroy");
        }

        $style->newLine();

        return Command::SUCCESS;
    }

    private function pluralize(string $word): string
    {
        if (str_ends_with($word, 's')) {
            return $word . 'es';
        }
        if (str_ends_with($word, 'y') && !str_ends_with($word, 'ey')) {
            return substr($word, 0, -1) . 'ies';
        }
        return $word . 's';
    }
}
