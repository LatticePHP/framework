<?php

declare(strict_types=1);

namespace Lattice\Validation\Tests\Fixtures;

use Lattice\Validation\Attributes\Required;

final class RequiredDto
{
    public function __construct(
        #[Required]
        public readonly mixed $value = null,
    ) {}
}
