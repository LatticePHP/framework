<?php

declare(strict_types=1);

namespace Tests\Integration\Fixtures;

use Lattice\OpenApi\Attributes\ApiOperation;
use Lattice\OpenApi\Attributes\ApiResponse;
use Lattice\Routing\Attributes\Controller;
use Lattice\Routing\Attributes\Delete;
use Lattice\Routing\Attributes\Get;
use Lattice\Routing\Attributes\Post;

#[Controller('/users')]
final class UserController
{
    #[Get]
    #[ApiOperation(summary: 'List all users', operationId: 'listUsers', tags: ['users'])]
    #[ApiResponse(status: 200, description: 'List of users')]
    public function index(): array
    {
        return [];
    }

    #[Get('/{id}')]
    #[ApiOperation(summary: 'Get a user by ID', operationId: 'getUser', tags: ['users'])]
    #[ApiResponse(status: 200, description: 'User details', type: UserDto::class)]
    #[ApiResponse(status: 404, description: 'User not found')]
    public function show(int $id): array
    {
        return [];
    }

    #[Post]
    #[ApiOperation(summary: 'Create a new user', operationId: 'createUser', tags: ['users'])]
    #[ApiResponse(status: 201, description: 'User created', type: UserDto::class)]
    public function store(CreateUserDto $dto): array
    {
        return [];
    }

    #[Delete('/{id}')]
    #[ApiOperation(summary: 'Delete a user', operationId: 'deleteUser', tags: ['users'])]
    #[ApiResponse(status: 204, description: 'User deleted')]
    public function destroy(int $id): void {}
}
