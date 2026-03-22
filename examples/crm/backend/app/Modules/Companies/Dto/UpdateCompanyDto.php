<?php

declare(strict_types=1);

namespace App\Modules\Companies\Dto;

use Lattice\Validation\Attributes\Email;
use Lattice\Validation\Attributes\InArray;
use Lattice\Validation\Attributes\Nullable;
use Lattice\Validation\Attributes\StringType;

final readonly class UpdateCompanyDto
{
    public function __construct(
        #[Nullable]
        #[StringType(minLength: 1, maxLength: 200)]
        public ?string $name = null,

        #[Nullable]
        #[StringType(maxLength: 100)]
        public ?string $domain = null,

        #[Nullable]
        #[InArray(values: ['technology', 'finance', 'healthcare', 'manufacturing', 'retail', 'education', 'consulting', 'other'])]
        public ?string $industry = null,

        #[Nullable]
        #[InArray(values: ['1-10', '11-50', '51-200', '201-500', '501-1000', '1001+'])]
        public ?string $size = null,

        #[Nullable]
        #[StringType(maxLength: 30)]
        public ?string $phone = null,

        #[Nullable]
        #[Email]
        public ?string $email = null,

        #[Nullable]
        #[StringType(maxLength: 255)]
        public ?string $address = null,

        #[Nullable]
        #[StringType(maxLength: 100)]
        public ?string $city = null,

        #[Nullable]
        #[StringType(maxLength: 100)]
        public ?string $state = null,

        #[Nullable]
        #[StringType(maxLength: 100)]
        public ?string $country = null,

        #[Nullable]
        #[StringType(maxLength: 255)]
        public ?string $website = null,
    ) {}
}
