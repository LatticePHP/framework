<?php

declare(strict_types=1);

namespace Lattice\Compiler\Tests\Fixtures;

use Lattice\Compiler\Attributes\Controller;

#[Controller(prefix: '/users')]
class UserController
{
    public function index(): array
    {
        return [];
    }
}
