<?php

declare(strict_types=1);

namespace Lattice\Core\Tests\Unit\Fixtures;

final class TestService
{
    public function greet(): string
    {
        return 'Hello from TestService';
    }
}
