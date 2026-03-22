<?php

declare(strict_types=1);

namespace Lattice\Validation\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
final class FloatType
{
    public function __construct(
        public readonly ?float $min = null,
        public readonly ?float $max = null,
    ) {}
}
