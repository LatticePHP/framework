<?php

declare(strict_types=1);

namespace Lattice\Validation\Tests\Fixtures;

use Lattice\Validation\Attributes\Url;

final class UrlDto
{
    public function __construct(
        #[Url]
        public readonly mixed $value = null,
    ) {}
}
