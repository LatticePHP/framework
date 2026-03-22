<?php

declare(strict_types=1);

namespace App\Modules\Notes\Dto;

use Lattice\Validation\Attributes\Nullable;
use Lattice\Validation\Attributes\StringType;

final readonly class UpdateNoteDto
{
    public function __construct(
        #[Nullable]
        #[StringType(minLength: 1, maxLength: 10000)]
        public ?string $body = null,

        #[Nullable]
        public ?bool $is_pinned = null,
    ) {}
}
