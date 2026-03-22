<?php

declare(strict_types=1);

namespace Lattice\Validation\Tests\Fixtures;

use Lattice\Validation\Attributes\StringType;

final class StringTypeDto
{
    public function __construct(
        #[StringType]
        public readonly mixed $value = null,
    ) {}
}
