<?php

declare(strict_types=1);

namespace Lattice\Validation\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
final class StringType
{
    public function __construct(
        public readonly ?int $minLength = null,
        public readonly ?int $maxLength = null,
        public readonly ?string $pattern = null,
    ) {}
}
