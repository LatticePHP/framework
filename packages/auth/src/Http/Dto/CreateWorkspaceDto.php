<?php

declare(strict_types=1);

namespace Lattice\Auth\Http\Dto;

use Lattice\Validation\Attributes\Required;
use Lattice\Validation\Attributes\StringType;

final class CreateWorkspaceDto
{
    #[Required]
    #[StringType(minLength: 1, maxLength: 255)]
    public string $name;

    #[StringType(minLength: 1, maxLength: 255)]
    public ?string $slug = null;

    #[StringType(maxLength: 2048)]
    public ?string $logo_url = null;

    /** @var array<string, mixed> */
    public array $settings = [];
}
