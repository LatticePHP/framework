<?php

declare(strict_types=1);

namespace Tests\Integration\Fixtures;

use Lattice\Workflow\Attributes\Activity;

#[Activity]
final class GreetingActivity
{
    public function compose(string $name): string
    {
        return 'Hello, ' . $name . '!';
    }

    public function farewell(string $name): string
    {
        return 'Goodbye, ' . $name . '!';
    }
}
