<?php

declare(strict_types=1);

namespace Lattice\Database\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
final class Transactional
{
    public function __construct(
        public readonly string $connection = 'default',
    ) {}
}
