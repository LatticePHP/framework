<?php

declare(strict_types=1);

namespace Lattice\Validation\Tests\Fixtures;

use Lattice\Validation\Attributes\ArrayType;
use Lattice\Validation\Attributes\BooleanType;
use Lattice\Validation\Attributes\DateTimeType;
use Lattice\Validation\Attributes\Email;
use Lattice\Validation\Attributes\FloatType;
use Lattice\Validation\Attributes\InArray;
use Lattice\Validation\Attributes\IntegerType;
use Lattice\Validation\Attributes\Nullable;
use Lattice\Validation\Attributes\Required;
use Lattice\Validation\Attributes\StringType;
use Lattice\Validation\Attributes\Url;
use Lattice\Validation\Attributes\Uuid;

final class FullFeaturedDto
{
    public function __construct(
        #[Required]
        #[StringType(minLength: 1, maxLength: 255)]
        public readonly string $name,

        #[Required]
        #[Email]
        public readonly string $email,

        #[Url]
        public readonly ?string $website = null,

        #[Uuid]
        public readonly ?string $externalId = null,

        #[IntegerType(min: 1, max: 100)]
        public readonly ?int $quantity = null,

        #[FloatType(min: 0.0, max: 999.99)]
        public readonly ?float $price = null,

        #[BooleanType]
        public readonly ?bool $active = null,

        #[InArray(values: ['admin', 'user', 'moderator'])]
        public readonly ?string $role = null,

        #[ArrayType(minItems: 1, maxItems: 5)]
        public readonly ?array $tags = null,

        #[DateTimeType(format: 'Y-m-d')]
        public readonly ?string $birthDate = null,

        #[Nullable]
        #[StringType(minLength: 1)]
        public readonly ?string $nickname = null,

        #[StringType(pattern: '/^[A-Z]{2,3}$/')]
        public readonly ?string $countryCode = null,
    ) {}
}
