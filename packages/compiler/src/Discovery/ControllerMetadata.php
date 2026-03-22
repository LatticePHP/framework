<?php

declare(strict_types=1);

namespace Lattice\Compiler\Discovery;

final class ControllerMetadata
{
    public function __construct(
        public readonly string $className,
        public readonly string $prefix = '',
    ) {}
}
