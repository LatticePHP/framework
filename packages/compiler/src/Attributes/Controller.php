<?php

declare(strict_types=1);

namespace Lattice\Compiler\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final class Controller
{
    public function __construct(
        public readonly string $prefix = '',
    ) {}
}
