<?php

declare(strict_types=1);

namespace Lattice\Workflow\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
final class SignalMethod
{
    public function __construct(
        public readonly ?string $name = null,
    ) {}
}
