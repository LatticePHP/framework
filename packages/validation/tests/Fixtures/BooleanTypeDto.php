<?php

declare(strict_types=1);

namespace Lattice\Validation\Tests\Fixtures;

use Lattice\Validation\Attributes\BooleanType;

final class BooleanTypeDto
{
    public function __construct(
        #[BooleanType]
        public readonly mixed $value = null,
    ) {}
}
