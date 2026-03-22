<?php

declare(strict_types=1);

namespace Lattice\Pipeline\Attributes;

use Attribute;
use Lattice\Contracts\Pipeline\GuardInterface;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final class UseGuards
{
    /** @param array<class-string<GuardInterface>> $guards */
    public function __construct(public readonly array $guards) {}
}
