<?php

declare(strict_types=1);

namespace Lattice\Auth\Http\Dto;

use Lattice\Validation\Attributes\Required;
use Lattice\Validation\Attributes\StringType;

final class UpdateMemberRoleDto
{
    #[Required]
    #[StringType(minLength: 1, maxLength: 50)]
    public string $role;
}
