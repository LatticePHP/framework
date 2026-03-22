<?php

declare(strict_types=1);

namespace Lattice\Pipeline\Attributes;

use Attribute;
use Lattice\Contracts\Pipeline\PipeInterface;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final class UsePipes
{
    /** @param array<class-string<PipeInterface>> $pipes */
    public function __construct(public readonly array $pipes) {}
}
