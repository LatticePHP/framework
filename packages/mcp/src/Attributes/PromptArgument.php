<?php

declare(strict_types=1);

namespace Lattice\Mcp\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PARAMETER)]
final class PromptArgument
{
    public function __construct(
        public readonly string $description = '',
        public readonly bool $required = true,
    ) {}
}
