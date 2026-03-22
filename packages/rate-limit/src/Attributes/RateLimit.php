<?php

declare(strict_types=1);

namespace Lattice\RateLimit\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final class RateLimit
{
    public function __construct(
        public readonly int $maxAttempts = 60,
        public readonly int $decaySeconds = 60,
        public readonly ?string $key = null,
    ) {}
}
