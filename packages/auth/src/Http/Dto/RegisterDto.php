<?php

declare(strict_types=1);

namespace Lattice\Auth\Http\Dto;

use Lattice\Validation\Attributes\Email;
use Lattice\Validation\Attributes\Required;
use Lattice\Validation\Attributes\StringType;

final class RegisterDto
{
    #[Required]
    #[StringType(minLength: 1, maxLength: 255)]
    public string $name;

    #[Required]
    #[Email]
    public string $email;

    #[Required]
    #[StringType(minLength: 8, maxLength: 255)]
    public string $password;
}
