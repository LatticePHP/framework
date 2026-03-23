<?php

declare(strict_types=1);

namespace App\Modules\Activities\Dto;

use App\Models\Activity;
use Lattice\Validation\Attributes\InArray;
use Lattice\Validation\Attributes\Nullable;
use Lattice\Validation\Attributes\Required;
use Lattice\Validation\Attributes\StringType;

final readonly class CreateActivityDto
{
    public function __construct(
        #[Required]
        #[InArray(values: Activity::TYPES)]
        public string $type,

        #[Required]
        #[StringType(minLength: 1, maxLength: 200)]
        public string $title,

        #[Nullable]
        #[StringType(maxLength: 2000)]
        public ?string $description = null,

        #[Nullable]
        public ?string $due_date = null,

        #[Nullable]
        public ?int $contact_id = null,

        #[Nullable]
        public ?int $deal_id = null,
    ) {}
}
