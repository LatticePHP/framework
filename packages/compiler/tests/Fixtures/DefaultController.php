<?php

declare(strict_types=1);

namespace Lattice\Compiler\Tests\Fixtures;

use Lattice\Compiler\Attributes\Controller;

#[Controller]
class DefaultController
{
    public function index(): string
    {
        return 'home';
    }
}
