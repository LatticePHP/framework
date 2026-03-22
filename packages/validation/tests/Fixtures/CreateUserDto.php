<?php

declare(strict_types=1);

namespace Lattice\Validation\Tests\Fixtures;

use Lattice\Validation\Attributes\Email;
use Lattice\Validation\Attributes\IntegerType;
use Lattice\Validation\Attributes\Required;
use Lattice\Validation\Attributes\StringType;

final class CreateUserDto
{
    public function __construct(
        #[Required]
        #[StringType(minLength: 2, maxLength: 100)]
        public readonly string $name,

        #[Required]
        #[Email]
        public readonly string $email,

        #[IntegerType(min: 0, max: 150)]
        public readonly ?int $age = null,
    ) {}
}
