<?php

declare(strict_types=1);

namespace App\Http;

use App\Dto\CreateUserDto;
use App\Dto\UserResource;
use Lattice\Routing\Attributes\Body;
use Lattice\Routing\Attributes\Controller;
use Lattice\Routing\Attributes\Delete;
use Lattice\Routing\Attributes\Get;
use Lattice\Routing\Attributes\Param;
use Lattice\Routing\Attributes\Post;
use Lattice\Routing\Attributes\Put;

#[Controller('/users')]
final class UserController
{
    #[Get('/')]
    public function index(): array
    {
        return [
            'data' => [],
            'meta' => ['total' => 0],
        ];
    }

    #[Get('/{id}')]
    public function show(#[Param] string $id): array
    {
        return UserResource::fromArray([
            'id' => $id,
            'name' => 'Example User',
            'email' => 'user@example.com',
        ]);
    }

    #[Post('/')]
    public function create(#[Body] CreateUserDto $dto): array
    {
        return [
            'data' => [
                'id' => bin2hex(random_bytes(16)),
                'name' => $dto->name,
                'email' => $dto->email,
            ],
            'status' => 'created',
        ];
    }

    #[Put('/{id}')]
    public function update(#[Param] string $id, #[Body] CreateUserDto $dto): array
    {
        return [
            'data' => [
                'id' => $id,
                'name' => $dto->name,
                'email' => $dto->email,
            ],
        ];
    }

    #[Delete('/{id}')]
    public function delete(#[Param] string $id): array
    {
        return ['status' => 'deleted', 'id' => $id];
    }
}
