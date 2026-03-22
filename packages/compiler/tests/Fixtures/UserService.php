<?php

declare(strict_types=1);

namespace Lattice\Compiler\Tests\Fixtures;

use Lattice\Compiler\Attributes\Injectable;

#[Injectable]
class UserService
{
    public function findAll(): array
    {
        return [];
    }
}
