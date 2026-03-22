<?php

declare(strict_types=1);

namespace Lattice\GraphQL\Tests\Fixtures;

use Lattice\GraphQL\Attributes\InputType;

#[InputType(name: 'CreateUserInput', description: 'Input for creating a user')]
final class CreateUserInput
{
    public string $name;
    public string $email;
    public ?string $username = null;
}
