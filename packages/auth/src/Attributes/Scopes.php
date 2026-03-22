<?php

declare(strict_types=1);

namespace Lattice\Auth\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final class Scopes
{
    public function __construct(
        public readonly array $scopes,
    ) {}
}
