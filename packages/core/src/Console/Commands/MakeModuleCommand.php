<?php

declare(strict_types=1);

namespace Lattice\Core\Console\Commands;

use Lattice\Core\Console\LatticeStyle;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class MakeModuleCommand extends Command
{
    public function __construct()
    {
        parent::__construct('make:module');
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Create a new LatticePHP module')
            ->addArgument('name', InputArgument::REQUIRED, 'The module name (PascalCase)')
            ->addOption('path', null, InputOption::VALUE_OPTIONAL, 'Base path for the module', 'src/Modules');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = (string) $input->getArgument('name');
        $basePath = (string) $input->getOption('path');
        $modulePath = $basePath . '/' . $name;
        $style = new LatticeStyle($output);

        $style->banner();
        $style->header("Creating module: {$name}");
        $style->newLine();

        $directories = [
            $modulePath,
            $modulePath . '/Controllers',
            $modulePath . '/Services',
            $modulePath . '/Entities',
            $modulePath . '/DTOs',
            $modulePath . '/Exceptions',
            $modulePath . '/Tests',
        ];

        foreach ($directories as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            $style->success("Created <fg=white>{$dir}</>");
        }

        $moduleContent = <<<PHP
            <?php

            declare(strict_types=1);

            namespace App\\Modules\\{$name};

            use Lattice\\Compiler\\Attributes\\Module;

            #[Module(
                imports: [],
                providers: [],
                controllers: [],
                exports: [],
            )]
            final class {$name}Module
            {
            }
            PHP;

        $moduleFile = $modulePath . '/' . $name . 'Module.php';
        file_put_contents($moduleFile, $moduleContent);
        $style->success("Created <fg=white>{$moduleFile}</>");

        $style->newLine();
        $style->info("Module <fg=white>{$name}</> scaffolded successfully");
        $style->info("Register it in your AppModule imports to activate");
        $style->newLine();

        return Command::SUCCESS;
    }
}
