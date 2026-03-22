<?php

declare(strict_types=1);

namespace Lattice\Module;

use Lattice\Contracts\Module\ModuleDefinitionInterface;

final class DynamicModuleDefinition implements ModuleDefinitionInterface
{
    /**
     * @param array<class-string> $imports
     * @param array<class-string> $providers
     * @param array<class-string> $controllers
     * @param array<class-string> $exports
     * @param array<string, callable> $factoryBindings
     */
    public function __construct(
        private readonly array $providers = [],
        private readonly array $imports = [],
        private readonly array $controllers = [],
        private readonly array $exports = [],
        private readonly array $factoryBindings = [],
    ) {}

    /**
     * Named constructor for the factory method pattern.
     *
     * @param array<class-string> $providers
     * @param array<class-string> $imports
     * @param array<class-string> $controllers
     * @param array<class-string> $exports
     * @param array<string, callable> $factoryBindings
     */
    public static function create(
        array $providers = [],
        array $imports = [],
        array $controllers = [],
        array $exports = [],
        array $factoryBindings = [],
    ): self {
        return new self(
            providers: $providers,
            imports: $imports,
            controllers: $controllers,
            exports: $exports,
            factoryBindings: $factoryBindings,
        );
    }

    /** @return array<class-string> */
    public function getImports(): array
    {
        return $this->imports;
    }

    /** @return array<class-string> */
    public function getProviders(): array
    {
        return $this->providers;
    }

    /** @return array<class-string> */
    public function getControllers(): array
    {
        return $this->controllers;
    }

    /** @return array<class-string> */
    public function getExports(): array
    {
        return $this->exports;
    }

    /** @return array<string, callable> */
    public function getFactoryBindings(): array
    {
        return $this->factoryBindings;
    }
}
