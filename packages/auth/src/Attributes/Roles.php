<?php

declare(strict_types=1);

namespace Lattice\Auth\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final class Roles
{
    public function __construct(
        public readonly array $roles,
    ) {}
}
