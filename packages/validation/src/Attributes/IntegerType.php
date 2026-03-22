<?php

declare(strict_types=1);

namespace Lattice\Validation\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
final class IntegerType
{
    public function __construct(
        public readonly ?int $min = null,
        public readonly ?int $max = null,
    ) {}
}
