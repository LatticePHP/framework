<?php

declare(strict_types=1);

namespace Lattice\Core\Features\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_CLASS)]
final class RequiresFeature
{
    public function __construct(
        public readonly string $feature,
    ) {}
}
