<?php

declare(strict_types=1);

namespace Lattice\Auth\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final class Policy
{
    public function __construct(
        public readonly string $model,
    ) {}
}
