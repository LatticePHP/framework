<?php

declare(strict_types=1);

namespace Lattice\Validation\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
final class ArrayType
{
    public function __construct(
        public readonly ?int $minItems = null,
        public readonly ?int $maxItems = null,
    ) {}
}
