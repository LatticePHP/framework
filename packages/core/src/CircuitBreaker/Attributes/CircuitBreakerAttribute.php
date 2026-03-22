<?php

declare(strict_types=1);

namespace Lattice\Core\CircuitBreaker\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
final class CircuitBreakerAttribute
{
    public function __construct(
        public readonly string $service,
        public readonly ?string $fallback = null,
    ) {}
}
