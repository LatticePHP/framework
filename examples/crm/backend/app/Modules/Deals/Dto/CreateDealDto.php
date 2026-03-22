<?php

declare(strict_types=1);

namespace App\Modules\Deals\Dto;

use Lattice\Validation\Attributes\InArray;
use Lattice\Validation\Attributes\Nullable;
use Lattice\Validation\Attributes\Required;
use Lattice\Validation\Attributes\StringType;

final readonly class CreateDealDto
{
    public function __construct(
        #[Required]
        #[StringType(minLength: 1, maxLength: 200)]
        public string $title,

        #[Required]
        public float $value,

        #[StringType(maxLength: 3)]
        public string $currency = 'USD',

        #[InArray(values: ['lead', 'qualified', 'proposal', 'negotiation', 'closed_won', 'closed_lost'])]
        public string $stage = 'lead',

        public int $probability = 0,

        #[Nullable]
        public ?string $expected_close_date = null,

        #[Nullable]
        public ?int $contact_id = null,

        #[Nullable]
        public ?int $company_id = null,
    ) {}
}
