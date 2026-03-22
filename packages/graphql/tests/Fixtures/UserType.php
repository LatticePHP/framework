<?php

declare(strict_types=1);

namespace Lattice\GraphQL\Tests\Fixtures;

use Lattice\GraphQL\Attributes\Field;
use Lattice\GraphQL\Attributes\ObjectType;

#[ObjectType(name: 'User', description: 'A user in the system')]
final class UserType
{
    #[Field(type: 'ID!', description: 'The user identifier')]
    public int $id;

    #[Field(description: 'The user email address')]
    public string $email;

    public string $name;

    #[Field(name: 'displayName', description: 'Formatted display name')]
    public string $displayLabel;

    #[Field(deprecationReason: 'Use email instead')]
    public ?string $username = null;

    public function __construct(int $id, string $email, string $name, string $displayLabel = '', ?string $username = null)
    {
        $this->id = $id;
        $this->email = $email;
        $this->name = $name;
        $this->displayLabel = $displayLabel;
        $this->username = $username;
    }

    #[Field(type: 'String!', description: 'The full greeting')]
    public function greeting(): string
    {
        return "Hello, {$this->name}!";
    }
}
