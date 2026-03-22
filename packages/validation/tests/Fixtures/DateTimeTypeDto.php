<?php

declare(strict_types=1);

namespace Lattice\Validation\Tests\Fixtures;

use Lattice\Validation\Attributes\DateTimeType;

final class DateTimeTypeDto
{
    public function __construct(
        #[DateTimeType]
        public readonly mixed $value = null,
    ) {}
}
