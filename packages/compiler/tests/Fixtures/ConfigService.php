<?php

declare(strict_types=1);

namespace Lattice\Compiler\Tests\Fixtures;

use Lattice\Compiler\Attributes\Injectable;

#[Injectable]
class ConfigService
{
    public function get(string $key): mixed
    {
        return null;
    }
}
