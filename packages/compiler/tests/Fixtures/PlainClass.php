<?php

declare(strict_types=1);

namespace Lattice\Compiler\Tests\Fixtures;

/**
 * A class with no framework attributes — should be ignored by the scanner.
 */
class PlainClass
{
    public function noop(): void
    {
    }
}
