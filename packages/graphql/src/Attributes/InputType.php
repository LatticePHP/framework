<?php

declare(strict_types=1);

namespace Lattice\GraphQL\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final class InputType
{
    public function __construct(
        public readonly ?string $name = null,
        public readonly ?string $description = null,
    ) {}
}
