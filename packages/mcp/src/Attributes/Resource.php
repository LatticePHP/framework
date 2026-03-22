<?php

declare(strict_types=1);

namespace Lattice\Mcp\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
final class Resource
{
    public function __construct(
        public readonly string $uri,
        public readonly ?string $name = null,
        public readonly string $description = '',
        public readonly string $mimeType = 'application/json',
    ) {}
}
