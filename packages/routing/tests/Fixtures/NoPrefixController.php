<?php

declare(strict_types=1);

namespace Lattice\Routing\Tests\Fixtures;

use Lattice\Routing\Attributes\Controller;
use Lattice\Routing\Attributes\Get;

#[Controller]
class NoPrefixController
{
    #[Get('/health')]
    public function health(): array
    {
        return ['status' => 'ok'];
    }
}
