<?php

declare(strict_types=1);

namespace Lattice\Mcp\Schema;

final class ParameterInfo
{
    public function __construct(
        public readonly string $name,
        public readonly ?\ReflectionType $type,
        public readonly bool $hasDefault,
        public readonly mixed $defaultValue,
        public readonly bool $nullable,
        public readonly string $description = '',
    ) {}
}
