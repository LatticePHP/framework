<?php

declare(strict_types=1);

namespace Lattice\Core\Support;

use Lattice\Contracts\Container\ContainerInterface;

abstract class ServiceProvider
{
    public function __construct(
        protected readonly ContainerInterface $container,
    ) {}

    /**
     * Register bindings into the container.
     */
    abstract public function register(): void;

    /**
     * Boot services after all providers have been registered.
     */
    public function boot(): void
    {
        // Default no-op; override in subclasses.
    }
}
