<?php

declare(strict_types=1);

namespace Lattice\Auth\Http\Dto;

use Lattice\Validation\Attributes\Required;
use Lattice\Validation\Attributes\StringType;

final class RefreshDto
{
    #[Required]
    #[StringType(minLength: 1)]
    public string $refresh_token;
}
