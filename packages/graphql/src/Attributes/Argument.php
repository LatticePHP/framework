<?php

declare(strict_types=1);

namespace Lattice\GraphQL\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PARAMETER)]
final class Argument
{
    public function __construct(
        public readonly ?string $name = null,
        public readonly ?string $type = null,
        public readonly ?string $description = null,
        public readonly mixed $defaultValue = null,
    ) {}
}
