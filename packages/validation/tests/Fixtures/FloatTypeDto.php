<?php

declare(strict_types=1);

namespace Lattice\Validation\Tests\Fixtures;

use Lattice\Validation\Attributes\FloatType;

final class FloatTypeDto
{
    public function __construct(
        #[FloatType]
        public readonly mixed $value = null,
    ) {}
}
