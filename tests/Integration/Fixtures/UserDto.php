<?php

declare(strict_types=1);

namespace Tests\Integration\Fixtures;

final readonly class UserDto
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        public ?string $bio = null,
    ) {}
}
