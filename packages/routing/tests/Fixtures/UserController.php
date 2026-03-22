<?php

declare(strict_types=1);

namespace Lattice\Routing\Tests\Fixtures;

use Lattice\Routing\Attributes\Controller;
use Lattice\Routing\Attributes\Get;
use Lattice\Routing\Attributes\Post;
use Lattice\Routing\Attributes\Put;
use Lattice\Routing\Attributes\Delete;
use Lattice\Routing\Attributes\Body;
use Lattice\Routing\Attributes\Query;
use Lattice\Routing\Attributes\Param;
use Lattice\Routing\Attributes\Header;
use Lattice\Routing\Attributes\CurrentUser;
use Lattice\Routing\Attributes\Version;

#[Controller('/users')]
#[Version('v1')]
class UserController
{
    #[Get]
    public function index(#[Query('limit')] ?int $limit = null): array
    {
        return [];
    }

    #[Get('/{id}')]
    public function show(#[Param('id')] int $id): array
    {
        return [];
    }

    #[Post]
    public function create(#[Body] array $data, #[CurrentUser] $user): array
    {
        return [];
    }

    #[Put('/{id}')]
    public function update(
        #[Param('id')] int $id,
        #[Body] array $data,
        #[Header('X-Request-Id')] string $requestId,
    ): array {
        return [];
    }

    #[Delete('/{id}')]
    public function destroy(#[Param('id')] int $id): array
    {
        return [];
    }
}
