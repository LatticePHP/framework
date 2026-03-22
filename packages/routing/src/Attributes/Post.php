<?php

declare(strict_types=1);

namespace Lattice\Routing\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
final class Post
{
    public function __construct(public readonly string $path = '') {}
}
