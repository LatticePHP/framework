<?php

declare(strict_types=1);

namespace Lattice\Validation\Tests\Fixtures;

use Lattice\Validation\Attributes\IntegerType;

final class IntegerTypeDto
{
    public function __construct(
        #[IntegerType]
        public readonly mixed $value = null,
    ) {}
}
