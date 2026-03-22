<?php

declare(strict_types=1);

namespace Lattice\Compiler\Discovery;

final class ModuleMetadata
{
    public function __construct(
        public readonly array $imports = [],
        public readonly array $providers = [],
        public readonly array $controllers = [],
        public readonly array $exports = [],
        public readonly bool $isGlobal = false,
    ) {}
}
