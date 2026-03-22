<?php

declare(strict_types=1);

namespace Lattice\Pipeline\Attributes;

use Attribute;
use Lattice\Contracts\Pipeline\ExceptionFilterInterface;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final class UseFilters
{
    /** @param array<class-string<ExceptionFilterInterface>> $filters */
    public function __construct(public readonly array $filters) {}
}
