<?php

declare(strict_types=1);

namespace Lattice\Validation\Tests\Fixtures;

use Lattice\Validation\Attributes\Nullable;
use Lattice\Validation\Attributes\StringType;

final class NullableDto
{
    public function __construct(
        #[Nullable]
        #[StringType]
        public readonly mixed $value = null,
    ) {}
}
