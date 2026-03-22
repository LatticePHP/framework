<?php

declare(strict_types=1);

namespace Lattice\Auth\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
final class Can
{
    public function __construct(
        public readonly string $ability,
    ) {}
}
