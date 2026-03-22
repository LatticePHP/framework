<?php

declare(strict_types=1);

namespace Lattice\Compiler\Graph;

final class ModuleNode
{
    public function __construct(
        public readonly string $className,
        public readonly array $imports = [],
        public readonly array $providers = [],
        public readonly array $controllers = [],
        public readonly array $exports = [],
        public readonly bool $isGlobal = false,
    ) {}

    /**
     * Serialize to a plain array for manifest compilation.
     */
    public function toArray(): array
    {
        return [
            'className' => $this->className,
            'imports' => $this->imports,
            'providers' => $this->providers,
            'controllers' => $this->controllers,
            'exports' => $this->exports,
            'isGlobal' => $this->isGlobal,
        ];
    }

    /**
     * Restore from a plain array.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            className: $data['className'],
            imports: $data['imports'] ?? [],
            providers: $data['providers'] ?? [],
            controllers: $data['controllers'] ?? [],
            exports: $data['exports'] ?? [],
            isGlobal: $data['isGlobal'] ?? false,
        );
    }
}
