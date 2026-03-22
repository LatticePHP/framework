<?php

declare(strict_types=1);

namespace Lattice\Core;

use Lattice\Contracts\Container\ContainerInterface;
use Lattice\Contracts\Pipeline\GuardInterface;

final class ApplicationBuilder
{
    /** @var list<class-string> */
    private array $modules = [];

    /** @var array<string, bool> */
    private array $transports = [];

    private bool $observability = false;

    private ?ContainerInterface $container = null;

    /** @var array<class-string<GuardInterface>> */
    private array $globalGuards = [];

    public function __construct(
        private readonly string $basePath,
    ) {}

    /**
     * @param list<class-string> $modules
     */
    public function withModules(array $modules): self
    {
        $this->modules = array_merge($this->modules, $modules);
        return $this;
    }

    public function withHttp(): self
    {
        $this->transports['http'] = true;
        return $this;
    }

    public function withGrpc(): self
    {
        $this->transports['grpc'] = true;
        return $this;
    }

    public function withObservability(): self
    {
        $this->observability = true;
        return $this;
    }

    /**
     * Use a specific container implementation.
     *
     * By default, Application uses IlluminateContainer (backed by illuminate/container).
     * Pass a lightweight Container instance for testing or minimal setups.
     */
    public function withContainer(ContainerInterface $container): self
    {
        $this->container = $container;
        return $this;
    }

    /**
     * Register global guards that execute on EVERY HTTP request before route-level guards.
     *
     * @param array<class-string<GuardInterface>> $guards
     */
    public function withGlobalGuards(array $guards): self
    {
        $this->globalGuards = array_merge($this->globalGuards, $guards);
        return $this;
    }

    public function create(): Application
    {
        return new Application(
            basePath: $this->basePath,
            modules: $this->modules,
            transports: $this->transports,
            observability: $this->observability,
            container: $this->container,
            globalGuards: $this->globalGuards,
        );
    }
}
