<?php

declare(strict_types=1);

namespace Lattice\Routing\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final class Version
{
    public function __construct(public readonly string|array $versions) {}
}
