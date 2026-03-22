<?php

declare(strict_types=1);

namespace Lattice\Mcp\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
final class Tool
{
    public function __construct(
        public readonly ?string $name = null,
        public readonly string $description = '',
    ) {}
}
