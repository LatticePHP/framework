<?php

declare(strict_types=1);

namespace Lattice\OpenApi\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class ApiResponse
{
    public function __construct(
        public readonly int $status = 200,
        public readonly ?string $description = null,
        public readonly ?string $type = null,
    ) {}
}
