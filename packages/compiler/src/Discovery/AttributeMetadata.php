<?php

declare(strict_types=1);

namespace Lattice\Compiler\Discovery;

final class AttributeMetadata
{
    public function __construct(
        public readonly string $className,
        public readonly bool $isModule = false,
        public readonly bool $isController = false,
        public readonly bool $isInjectable = false,
        public readonly bool $isGlobal = false,
        public readonly array $imports = [],
        public readonly array $providers = [],
        public readonly array $controllers = [],
        public readonly array $exports = [],
        public readonly string $controllerPrefix = '',
    ) {}
}
