<?php

declare(strict_types=1);

namespace Lattice\GraphQL\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
final class Mutation
{
    public function __construct(
        public readonly ?string $name = null,
        public readonly ?string $description = null,
    ) {}
}
