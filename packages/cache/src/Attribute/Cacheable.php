<?php

declare(strict_types=1);

namespace Lattice\Cache\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
final class Cacheable
{
    public function __construct(
        public readonly int $ttl = 3600,
        public readonly ?string $key = null,
    ) {}
}
