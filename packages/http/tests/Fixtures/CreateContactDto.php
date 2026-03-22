<?php

declare(strict_types=1);

namespace Lattice\Http\Tests\Fixtures;

use Lattice\Validation\Attributes\Email;
use Lattice\Validation\Attributes\Required;
use Lattice\Validation\Attributes\StringType;

final class CreateContactDto
{
    public function __construct(
        #[Required]
        #[StringType(minLength: 1)]
        public readonly string $first_name,

        #[Required]
        #[StringType(minLength: 1)]
        public readonly string $last_name,

        #[Required]
        #[Email]
        public readonly string $email,
    ) {}
}
