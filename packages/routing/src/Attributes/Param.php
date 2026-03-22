<?php

declare(strict_types=1);

namespace Lattice\Routing\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PARAMETER)]
final class Param
{
    public function __construct(public readonly ?string $name = null) {}
}
