<?php

declare(strict_types=1);

namespace Lattice\Core\Tests\Integration\Fixtures;

use Lattice\Http\Response;
use Lattice\Routing\Attributes\Body;
use Lattice\Routing\Attributes\Controller;
use Lattice\Routing\Attributes\Get;
use Lattice\Routing\Attributes\Param;
use Lattice\Routing\Attributes\Post;

#[Controller('/lifecycle')]
final class LifecycleTestController
{
    #[Get('/health')]
    public function health(): array
    {
        return ['status' => 'ok'];
    }

    #[Get('/users/{id}')]
    public function getUser(#[Param] string $id): array
    {
        return ['id' => $id];
    }

    #[Post('/echo')]
    public function echo(#[Body] array $body): array
    {
        return $body;
    }

    #[Get('/error')]
    public function error(): never
    {
        throw new \RuntimeException('Something went wrong');
    }
}
