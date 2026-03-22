<?php

declare(strict_types=1);

namespace Lattice\Compiler\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final class Module
{
    public function __construct(
        public readonly array $imports = [],
        public readonly array $providers = [],
        public readonly array $controllers = [],
        public readonly array $exports = [],
        public readonly bool $global = false,
    ) {}
}
