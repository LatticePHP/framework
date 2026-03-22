<?php

declare(strict_types=1);

namespace Lattice\Validation\Tests\Fixtures;

use Lattice\Validation\Attributes\ArrayType;

final class ArrayTypeDto
{
    public function __construct(
        #[ArrayType]
        public readonly mixed $value = null,
    ) {}
}
