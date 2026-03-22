<?php

declare(strict_types=1);

namespace Lattice\Mcp\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PARAMETER)]
final class ToolParam
{
    public function __construct(
        public readonly string $description = '',
    ) {}
}
