<?php

declare(strict_types=1);

namespace App\Modules\Notes\Dto;

use App\Models\Note;
use Lattice\Validation\Attributes\InArray;
use Lattice\Validation\Attributes\Nullable;
use Lattice\Validation\Attributes\Required;
use Lattice\Validation\Attributes\StringType;

final readonly class CreateNoteDto
{
    public function __construct(
        #[Required]
        #[StringType(minLength: 1, maxLength: 10000)]
        public string $body,

        #[Required]
        #[InArray(values: Note::NOTABLE_TYPES)]
        public string $notable_type,

        #[Required]
        public int $notable_id,

        #[Nullable]
        public ?bool $is_pinned = false,
    ) {}
}
