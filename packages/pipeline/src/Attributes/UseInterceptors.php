<?php

declare(strict_types=1);

namespace Lattice\Pipeline\Attributes;

use Attribute;
use Lattice\Contracts\Pipeline\InterceptorInterface;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final class UseInterceptors
{
    /** @param array<class-string<InterceptorInterface>> $interceptors */
    public function __construct(public readonly array $interceptors) {}
}
