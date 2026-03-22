<?php

declare(strict_types=1);

namespace Lattice\Compiler\Tests\Fixtures;

use Lattice\Compiler\Attributes\Injectable;

#[Injectable]
class SimpleService
{
    public function doSomething(): string
    {
        return 'done';
    }
}
