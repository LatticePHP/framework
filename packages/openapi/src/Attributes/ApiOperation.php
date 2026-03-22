<?php

declare(strict_types=1);

namespace Lattice\OpenApi\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
final class ApiOperation
{
    public function __construct(
        public readonly ?string $summary = null,
        public readonly ?string $description = null,
        public readonly ?string $operationId = null,
        public readonly array $tags = [],
    ) {}
}
