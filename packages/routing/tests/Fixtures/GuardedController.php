<?php

declare(strict_types=1);

namespace Lattice\Routing\Tests\Fixtures;

use Lattice\Pipeline\Attributes\UseGuards;
use Lattice\Pipeline\Attributes\UseInterceptors;
use Lattice\Pipeline\Attributes\UsePipes;
use Lattice\Pipeline\Attributes\UseFilters;
use Lattice\Routing\Attributes\Controller;
use Lattice\Routing\Attributes\Get;
use Lattice\Routing\Attributes\Name;
use Lattice\Routing\Attributes\Post;
use Lattice\Routing\Attributes\Version;

#[Controller('/admin')]
#[Version('v2')]
#[UseGuards(['App\\Guards\\AuthGuard', 'App\\Guards\\RoleGuard'])]
#[UseInterceptors(['App\\Interceptors\\LoggingInterceptor'])]
class GuardedController
{
    #[Get]
    #[Name('admin.dashboard')]
    public function dashboard(): array
    {
        return ['page' => 'dashboard'];
    }

    #[Post('/action')]
    #[Name('admin.action')]
    #[UseGuards(['App\\Guards\\CsrfGuard'])]
    public function action(): array
    {
        return ['result' => 'ok'];
    }

    #[Get('/stats')]
    public function stats(): array
    {
        return ['stats' => []];
    }
}
