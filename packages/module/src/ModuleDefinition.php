<?php

declare(strict_types=1);

namespace Lattice\Module;

use Lattice\Contracts\Module\ModuleDefinitionInterface;
use Lattice\Module\Attribute\Module;

final class ModuleDefinition implements ModuleDefinitionInterface
{
    /**
     * @param array<class-string> $imports
     * @param array<class-string> $providers
     * @param array<class-string> $controllers
     * @param array<class-string> $exports
     */
    public function __construct(
        private readonly array $imports = [],
        private readonly array $providers = [],
        private readonly array $controllers = [],
        private readonly array $exports = [],
    ) {}

    public static function fromAttribute(Module $attribute): self
    {
        return new self(
            imports: $attribute->imports,
            providers: $attribute->providers,
            controllers: $attribute->controllers,
            exports: $attribute->exports,
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
}
