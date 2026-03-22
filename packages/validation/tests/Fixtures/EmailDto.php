<?php

declare(strict_types=1);

namespace Lattice\Validation\Tests\Fixtures;

use Lattice\Validation\Attributes\Email;

final class EmailDto
{
    public function __construct(
        #[Email]
        public readonly mixed $value = null,
    ) {}
}
