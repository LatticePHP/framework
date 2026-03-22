<?php

declare(strict_types=1);

namespace Lattice\DevTools\Console\Illuminate;

use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Base command for LatticePHP that extends Symfony Command.
 *
 * Subclasses implement handle() instead of execute() for a cleaner API.
 * This mirrors Illuminate's Artisan command pattern.
 */
abstract class LatticeCommand extends SymfonyCommand
{
    /**
     * Execute the command logic.
     */
    abstract protected function handle(InputInterface $input, OutputInterface $output): int;

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->handle($input, $output);
    }
}
