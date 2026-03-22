<?php

declare(strict_types=1);

namespace Lattice\Serializer\Tests\Fixtures;

final class SampleDto
{
    public function __construct(
        public readonly string $name,
        public readonly int $age,
        public readonly string $email = 'default@example.com',
    ) {}
}
