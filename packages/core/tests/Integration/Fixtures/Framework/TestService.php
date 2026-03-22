<?php

declare(strict_types=1);

namespace Lattice\Core\Tests\Integration\Fixtures\Framework;

/**
 * A simple service to test constructor injection in controllers.
 */
final class TestService
{
    public function greet(string $name): string
    {
        return "Hello, {$name}!";
    }
}
