<?php

declare(strict_types=1);

namespace App\Modules\Deals\Dto;

use App\Models\Deal;
use Lattice\Validation\Attributes\InArray;
use Lattice\Validation\Attributes\Nullable;
use Lattice\Validation\Attributes\StringType;

final readonly class UpdateDealDto
{
    public function __construct(
        #[Nullable]
        #[StringType(minLength: 1, maxLength: 200)]
        public ?string $title = null,

        #[Nullable]
        public ?float $value = null,

        #[Nullable]
        #[StringType(maxLength: 3)]
        public ?string $currency = null,

        #[Nullable]
        #[InArray(values: Deal::STAGES)]
        public ?string $stage = null,

        #[Nullable]
        public ?int $probability = null,

        #[Nullable]
        public ?string $expected_close_date = null,

        #[Nullable]
        public ?string $actual_close_date = null,

        #[Nullable]
        public ?int $contact_id = null,

        #[Nullable]
        public ?int $company_id = null,

        #[Nullable]
        #[StringType(maxLength: 500)]
        public ?string $lost_reason = null,
    ) {}
}
