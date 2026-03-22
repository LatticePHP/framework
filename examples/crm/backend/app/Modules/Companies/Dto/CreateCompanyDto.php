<?php

declare(strict_types=1);

namespace App\Modules\Companies\Dto;

use Lattice\Validation\Attributes\Email;
use Lattice\Validation\Attributes\Nullable;
use Lattice\Validation\Attributes\Required;
use Lattice\Validation\Attributes\StringType;

final readonly class CreateCompanyDto
{
    public function __construct(
        #[Required]
        #[StringType(minLength: 1, maxLength: 200)]
        public string $name,

        #[Nullable]
        #[StringType(maxLength: 100)]
        public ?string $domain = null,

        #[Nullable]
        #[StringType(maxLength: 100)]
        public ?string $industry = null,

        #[Nullable]
        #[StringType(maxLength: 50)]
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
