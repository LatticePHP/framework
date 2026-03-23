<?php

declare(strict_types=1);

namespace App\Modules\Contacts\Dto;

use App\Models\Contact;
use Lattice\Validation\Attributes\Email;
use Lattice\Validation\Attributes\InArray;
use Lattice\Validation\Attributes\Nullable;
use Lattice\Validation\Attributes\Required;
use Lattice\Validation\Attributes\StringType;

final readonly class CreateContactDto
{
    public function __construct(
        #[Required]
        #[StringType(minLength: 1, maxLength: 100)]
        public string $first_name,

        #[Required]
        #[StringType(minLength: 1, maxLength: 100)]
        public string $last_name,

        #[Required]
        #[Email]
        public string $email,

        #[Nullable]
        #[StringType(maxLength: 30)]
        public ?string $phone = null,

        #[Nullable]
        public ?int $company_id = null,

        #[Nullable]
        #[StringType(maxLength: 100)]
        public ?string $title = null,

        #[InArray(values: Contact::STATUSES)]
        public string $status = 'lead',

        #[Nullable]
        #[InArray(values: Contact::SOURCES)]
        public ?string $source = null,

        #[Nullable]
        public ?array $tags = null,
    ) {}
}
