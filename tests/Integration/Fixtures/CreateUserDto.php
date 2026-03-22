<?php

declare(strict_types=1);

namespace Tests\Integration\Fixtures;

final readonly class CreateUserDto
{
    public function __construct(
        public string $name,
        public string $email,
    ) {}
}
