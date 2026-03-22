<?php

declare(strict_types=1);

namespace Lattice\Http\Tests\Fixtures;

final class ContactFilterDto
{
    public function __construct(
        public readonly int $page = 1,
        public readonly int $limit = 10,
        public readonly ?string $search = null,
    ) {}
}
