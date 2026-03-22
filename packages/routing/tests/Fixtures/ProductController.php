<?php

declare(strict_types=1);

namespace Lattice\Routing\Tests\Fixtures;

use Lattice\Routing\Attributes\Controller;
use Lattice\Routing\Attributes\Get;
use Lattice\Routing\Attributes\Post;
use Lattice\Routing\Attributes\Patch;

#[Controller('/products')]
class ProductController
{
    #[Get]
    public function list(): array
    {
        return [];
    }

    #[Get('/{id}')]
    public function show(#[\Lattice\Routing\Attributes\Param('id')] int $id): array
    {
        return [];
    }

    #[Post]
    public function create(#[\Lattice\Routing\Attributes\Body] array $data): array
    {
        return [];
    }

    #[Patch('/{id}')]
    public function patch(
        #[\Lattice\Routing\Attributes\Param('id')] int $id,
        #[\Lattice\Routing\Attributes\Body] array $data,
    ): array {
        return [];
    }
}
