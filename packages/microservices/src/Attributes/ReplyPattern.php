<?php

declare(strict_types=1);

namespace Lattice\Microservices\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
final class ReplyPattern
{
    public function __construct(
        public readonly string $pattern,
    ) {}
}
