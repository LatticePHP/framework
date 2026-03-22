<?php

declare(strict_types=1);

namespace Lattice\DevTools\Console\Illuminate;

use Symfony\Component\Console\Application as SymfonyApp;
use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Wraps Symfony Console (which Illuminate uses underneath) for LatticePHP.
 *
 * This is the recommended production console entry point. It provides full
 * Symfony Console features: auto-completion, table output, progress bars,
 * interactive questions, and more.
 *
 * The lightweight Lattice\DevTools\Console\Application remains available
 * as a fallback for simple use cases.
 */
final class IlluminateConsoleApplication
{
    private SymfonyApp $app;

    public function __construct(string $name = 'Lattice', string $version = '1.0.0')
    {
        $this->app = new SymfonyApp($name, $version);
    }

    public function add(SymfonyCommand $command): void
    {
        $this->app->add($command);
    }

    public function addCommands(array $commands): void
    {
        foreach ($commands as $command) {
            $this->app->add($command);
        }
    }

    public function run(?InputInterface $input = null, ?OutputInterface $output = null): int
    {
        return $this->app->run($input, $output);
    }

    public function getApplication(): SymfonyApp
    {
        return $this->app;
    }
}
