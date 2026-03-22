<?php

declare(strict_types=1);

namespace Lattice\Anvil;

use Lattice\Anvil\Console\AnvilDeployCommand;
use Lattice\Anvil\Console\AnvilRollbackCommand;
use Lattice\Anvil\Console\AnvilStatusCommand;
use Lattice\Anvil\Detection\SystemDetector;
use Lattice\Contracts\Container\ContainerInterface;

final class AnvilServiceProvider
{
    public function register(ContainerInterface $container): void
    {
        $container->singleton(SystemDetector::class, SystemDetector::class);

        $container->bind(AnvilStatusCommand::class, function () use ($container): AnvilStatusCommand {
            return new AnvilStatusCommand(
                $container->get(SystemDetector::class),
            );
        });

        $container->bind(AnvilDeployCommand::class, function (): AnvilDeployCommand {
            return new AnvilDeployCommand();
        });

        $container->bind(AnvilRollbackCommand::class, function (): AnvilRollbackCommand {
            return new AnvilRollbackCommand();
        });
    }
}
