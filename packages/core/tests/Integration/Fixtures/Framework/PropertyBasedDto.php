<?php

declare(strict_types=1);

namespace Lattice\Core\Tests\Integration\Fixtures\Framework;

use Lattice\Validation\Attributes\Email;
use Lattice\Validation\Attributes\Required;
use Lattice\Validation\Attributes\StringType;

/**
 * Property-based DTO (no constructor) for testing the DtoMapper bug.
 * DtoMapper must handle classes without constructors by setting public properties.
 */
final class PropertyBasedDto
{
    #[Required] #[StringType] public string $name;
    #[Required] #[Email] public string $email;
}
