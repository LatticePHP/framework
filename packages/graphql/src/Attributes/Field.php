<?php

declare(strict_types=1);

namespace Lattice\GraphQL\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_METHOD)]
final class Field
{
    public function __construct(
        public readonly ?string $name = null,
        public readonly ?string $type = null,
        public readonly ?string $description = null,
        public readonly ?string $deprecationReason = null,
        public readonly bool $nullable = false,
    ) {}
}
