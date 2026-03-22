<?php

declare(strict_types=1);

namespace Lattice\Core\Console;

use Symfony\Component\Console\Application as SymfonyConsole;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class LatticeApplication
{
    private SymfonyConsole $console;
    private string $basePath;

    private function __construct()
    {
    }

    public static function create(string $basePath): self
    {
        $instance = new self();
        $instance->basePath = $basePath;
        $instance->console = new SymfonyConsole(
            "\u{2B21} LatticePHP",
            '1.0.0',
        );

        $instance->registerCoreCommands();

        return $instance;
    }

    public function run(?InputInterface $input = null, ?OutputInterface $output = null): int
    {
        return $this->console->run($input, $output);
    }

    public function getConsole(): SymfonyConsole
    {
        return $this->console;
    }

    public function getBasePath(): string
    {
        return $this->basePath;
    }

    /**
     * Register commands that can be added later (e.g., DI-dependent commands).
     *
     * @param \Symfony\Component\Console\Command\Command ...$commands
     */
    public function addCommands(\Symfony\Component\Console\Command\Command ...$commands): void
    {
        foreach ($commands as $command) {
            $this->console->addCommand($command);
        }
    }

    private function registerCoreCommands(): void
    {
        // Only register commands that have no required constructor dependencies.
        // DI-dependent commands (migrate, queue, schedule, db:seed) must be
        // registered via addCommands() after the container is built.
        $this->console->addCommands([
            new Commands\ServeCommand(),
            new Commands\MakeModuleCommand(),
            new Commands\MakeControllerCommand(),
            new Commands\MakeModelCommand(),
            new Commands\MakeDtoCommand(),
            new Commands\MakePolicyCommand(),
            new Commands\MakeWorkflowCommand(),
            new Commands\RouteListCommand(),
            new Commands\ModuleListCommand(),
            new Commands\TestCommand(),
        ]);
    }
}
