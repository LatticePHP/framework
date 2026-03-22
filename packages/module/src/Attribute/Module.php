<?php

declare(strict_types=1);

namespace Lattice\Module\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final class Module
{
    /**
     * @param array<class-string> $imports
     * @param array<class-string> $providers
     * @param array<class-string> $controllers
     * @param array<class-string> $exports
     */
    public function __construct(
        public readonly array $imports = [],
        public readonly array $providers = [],
        public readonly array $controllers = [],
        public readonly array $exports = [],
    ) {}
}
