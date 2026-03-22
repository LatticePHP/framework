<?php

declare(strict_types=1);

namespace Lattice\Core\Tests\Integration\Fixtures\Framework;

use Lattice\Validation\Attributes\Email;
use Lattice\Validation\Attributes\InArray;
use Lattice\Validation\Attributes\Required;
use Lattice\Validation\Attributes\StringType;

/**
 * Constructor-based DTO for testing body deserialization.
 */
final class CreateTestContactDto
{
    public function __construct(
        #[Required] #[StringType] public readonly string $name,
        #[Required] #[Email] public readonly string $email,
        #[InArray(['active', 'inactive'])] public readonly string $status = 'active',
    ) {}
}
