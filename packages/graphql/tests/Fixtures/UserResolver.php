<?php

declare(strict_types=1);

namespace Lattice\GraphQL\Tests\Fixtures;

use Lattice\GraphQL\Attributes\Argument;
use Lattice\GraphQL\Attributes\Mutation;
use Lattice\GraphQL\Attributes\Query;

final class UserResolver
{
    /** @var array<int, UserType> */
    private array $users = [];

    public function __construct()
    {
        $this->users = [
            1 => new UserType(1, 'alice@example.com', 'Alice', 'Alice A.', 'alice'),
            2 => new UserType(2, 'bob@example.com', 'Bob', 'Bob B.', 'bob'),
            3 => new UserType(3, 'charlie@example.com', 'Charlie', 'Charlie C.', 'charlie'),
        ];
    }

    /**
     * @return array<UserType>
     */
    #[Query(name: 'users', description: 'Get all users')]
    public function getUsers(): array
    {
        return array_values($this->users);
    }

    #[Query(name: 'user', description: 'Get a user by ID')]
    public function getUser(
        #[Argument(name: 'id', type: 'ID!', description: 'The user ID')]
        int $id,
    ): ?UserType {
        return $this->users[$id] ?? null;
    }

    #[Mutation(name: 'createUser', description: 'Create a new user')]
    public function createUser(
        string $name,
        string $email,
    ): UserType {
        $id = max(array_keys($this->users)) + 1;
        $user = new UserType($id, $email, $name, $name);
        $this->users[$id] = $user;

        return $user;
    }

    #[Mutation(name: 'deleteUser', description: 'Delete a user by ID')]
    public function deleteUser(int $id): bool
    {
        if (isset($this->users[$id])) {
            unset($this->users[$id]);
            return true;
        }

        return false;
    }
}
