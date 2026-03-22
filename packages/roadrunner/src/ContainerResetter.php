<?php

declare(strict_types=1);

namespace Lattice\RoadRunner;

use Lattice\Contracts\Container\ContainerInterface;

final class ContainerResetter
{
    /** @var list<callable> */
    private array $resetCallbacks = [];

    public function addResetCallback(callable $callback): void
    {
        $this->resetCallbacks[] = $callback;
    }

    public function reset(ContainerInterface $container): void
    {
        foreach ($this->resetCallbacks as $callback) {
            $callback();
        }

        $container->reset();
    }
}
