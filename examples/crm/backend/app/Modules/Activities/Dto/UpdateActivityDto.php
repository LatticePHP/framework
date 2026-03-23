<?php

declare(strict_types=1);

namespace App\Modules\Activities\Dto;

use App\Models\Activity;
use Lattice\Validation\Attributes\InArray;
use Lattice\Validation\Attributes\Nullable;
use Lattice\Validation\Attributes\StringType;

final readonly class UpdateActivityDto
{
    public function __construct(
        #[Nullable]
        #[InArray(values: Activity::TYPES)]
        public ?string $type = null,

        #[Nullable]
        #[StringType(minLength: 1, maxLength: 200)]
        public ?string $title = null,

        #[Nullable]
        #[StringType(maxLength: 2000)]
        public ?string $description = null,

        #[Nullable]
        public ?string $due_date = null,

        #[Nullable]
        public ?int $contact_id = null,

        #[Nullable]
        public ?int $deal_id = null,

        #[Nullable]
        public ?bool $completed = null,
    ) {}
}
