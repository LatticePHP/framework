<?php

declare(strict_types=1);

namespace App\Dto;

use Lattice\Validation\Attributes\Email;
use Lattice\Validation\Attributes\Required;
use Lattice\Validation\Attributes\StringType;

final class CreateUserDto
{
    public function __construct(
        #[Required]
        #[StringType]
        public readonly string $name,

        #[Required]
        #[Email]
        public readonly string $email,
    ) {}
}
