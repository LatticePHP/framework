<?php

declare(strict_types=1);

namespace Lattice\Core\Container;

use Illuminate\Container\Container as BaseContainer;
use Lattice\Contracts\Container\ContainerInterface;

/**
 * Production-recommended DI container backed by Laravel's Illuminate Container.
 *
 * Provides the full power of Illuminate's DI: contextual binding, tagged services,
 * method injection, auto-wiring, and more — while conforming to Lattice's ContainerInterface.
 *
 * The lightweight {@see Container} remains available for testing and minimal setups.
 */
final class IlluminateContainer implements ContainerInterface
{
    private BaseContainer $container;

    public function __construct(?BaseContainer $container = null)
    {
        $this->container = $container ?? new BaseContainer();
    }

    public function bind(string $abstract, mixed $concrete = null): void
    {
        $this->container->bind($abstract, $concrete);
    }

    public function singleton(string $abstract, mixed $concrete = null): void
    {
        $this->container->singleton($abstract, $concrete);
    }

    public function instance(string $abstract, mixed $instance): void
    {
        $this->container->instance($abstract, $instance);
    }

    public function make(string $abstract, array $parameters = []): mixed
    {
        return $this->container->make($abstract, $parameters);
    }

    public function get(string $id): mixed
    {
        return $this->container->get($id);
    }

    public function has(string $id): bool
    {
        return $this->container->has($id);
    }

    public function reset(): void
    {
        $this->container->flush();
    }

    /**
     * Access the underlying Illuminate Container for advanced features:
     * contextual binding, tagged services, method injection, etc.
     */
    public function getIlluminateContainer(): BaseContainer
    {
        return $this->container;
    }
}
