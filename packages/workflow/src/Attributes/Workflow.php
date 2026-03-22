<?php

declare(strict_types=1);

namespace Lattice\Workflow\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final class Workflow
{
    public function __construct(
        public readonly ?string $name = null,
        public readonly ?string $taskQueue = 'default',
    ) {}
}
