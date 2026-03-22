<?php

declare(strict_types=1);

namespace Lattice\Module\Provider;

use Lattice\Contracts\Container\ContainerInterface;

final class ProviderRegistry
{
    /** @var array<string, list<string>> Module class name -> provider abstracts */
    private array $providers = [];

    /** @var array<string, list<string>> Module class name -> exported provider abstracts */
    private array $exports = [];

    /**
     * Register class-based providers for a module.
     *
     * @param string $module
     * @param array<class-string> $providerClasses
     * @param ContainerInterface $container
     */
    public function registerProviders(
        string $module,
        array $providerClasses,
        ContainerInterface $container,
    ): void {
        foreach ($providerClasses as $providerClass) {
            $container->bind($providerClass, $providerClass);
            $this->providers[$module][] = $providerClass;
        }
    }

    /**
     * Register a factory binding for a module.
     */
    public function registerFactory(
        string $module,
        string $abstract,
        callable $factory,
        ContainerInterface $container,
    ): void {
        $container->bind($abstract, $factory);
        $this->providers[$module][] = $abstract;
    }

    /**
     * Register a value (instance) binding for a module.
     */
    public function registerValue(
        string $module,
        string $abstract,
        mixed $value,
        ContainerInterface $container,
    ): void {
        $container->instance($abstract, $value);
        $this->providers[$module][] = $abstract;
    }

    /**
     * Register an alias binding for a module.
     */
    public function registerAlias(
        string $module,
        string $abstract,
        string $concrete,
        ContainerInterface $container,
    ): void {
        $container->bind($abstract, $concrete);
        $this->providers[$module][] = $abstract;
    }

    /**
     * Mark certain providers as exported (visible cross-module).
     *
     * @param string $module
     * @param array<class-string> $exportedClasses
     */
    public function markExported(string $module, array $exportedClasses): void
    {
        $this->exports[$module] = $exportedClasses;
    }

    /**
     * Get all provider abstracts registered for a module.
     *
     * @return list<string>
     */
    public function getProvidersForModule(string $module): array
    {
        return $this->providers[$module] ?? [];
    }

    /**
     * Get only the exported providers for a module.
     *
     * @return list<string>
     */
    public function getExportedProviders(string $module): array
    {
        return $this->exports[$module] ?? [];
    }
}
