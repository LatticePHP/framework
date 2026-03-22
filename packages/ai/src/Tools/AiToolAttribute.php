<?php

declare(strict_types=1);

namespace Lattice\Ai\Tools;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
final class AiToolAttribute
{
    public function __construct(
        public readonly string $name,
        public readonly string $description = '',
    ) {}
}
