<?php

declare(strict_types=1);

namespace Lattice\Validation\Tests\Fixtures;

use Lattice\Validation\Attributes\Uuid;

final class UuidDto
{
    public function __construct(
        #[Uuid]
        public readonly mixed $value = null,
    ) {}
}
