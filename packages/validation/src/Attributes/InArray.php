<?php

declare(strict_types=1);

namespace Lattice\Validation\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
final class InArray
{
    public function __construct(public readonly array $values)
    {
    }
}
