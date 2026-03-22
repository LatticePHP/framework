<?php

declare(strict_types=1);

namespace Lattice\Validation\Tests\Fixtures;

use Lattice\Validation\Attributes\InArray;

final class InArrayDto
{
    public function __construct(
        #[InArray(values: ['red', 'green', 'blue'])]
        public readonly mixed $value = null,
    ) {}
}
