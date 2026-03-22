<?php

declare(strict_types=1);

namespace Lattice\DevTools\Console\Illuminate;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Scaffolds a new LatticePHP module with the standard directory structure.
 *
 * Usage: lattice make:module UserManagement
 */
final class MakeModuleCommand extends LatticeCommand
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
            ->addOption('path', 'p', InputOption::VALUE_OPTIONAL, 'Base path for the module', 'src/Modules');
    }

    protected function handle(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getArgument('name');
        $basePath = $input->getOption('path');
        $modulePath = $basePath . '/' . $name;

        $directories = [
            $modulePath,
            $modulePath . '/Controllers',
            $modulePath . '/Services',
            $modulePath . '/Entities',
            $modulePath . '/DTOs',
            $modulePath . '/Exceptions',
        ];

        foreach ($directories as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }

        // Create the module class
        $moduleContent = <<<PHP
        <?php

        declare(strict_types=1);

        namespace App\\Modules\\{$name};

        use Lattice\\Module\\Attributes\\Module;

        #[Module]
        final class {$name}Module
        {
        }
        PHP;

        file_put_contents($modulePath . '/' . $name . 'Module.php', $moduleContent);

        $output->writeln("<info>Module '{$name}' created at {$modulePath}</info>");

        return self::SUCCESS;
    }
}
