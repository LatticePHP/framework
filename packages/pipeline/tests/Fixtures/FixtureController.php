<?php

declare(strict_types=1);

namespace Lattice\Pipeline\Tests\Fixtures;

use Lattice\Contracts\Context\ExecutionContextInterface;
use Lattice\Contracts\Pipeline\ExceptionFilterInterface;
use Lattice\Contracts\Pipeline\GuardInterface;
use Lattice\Contracts\Pipeline\InterceptorInterface;
use Lattice\Contracts\Pipeline\PipeInterface;
use Lattice\Pipeline\Attributes\UseFilters;
use Lattice\Pipeline\Attributes\UseGuards;
use Lattice\Pipeline\Attributes\UseInterceptors;
use Lattice\Pipeline\Attributes\UsePipes;

class StubGuard implements GuardInterface
{
    public function canActivate(ExecutionContextInterface $context): bool
    {
        return true;
    }
}

class StubPipe implements PipeInterface
{
    public function transform(mixed $value, array $metadata = []): mixed
    {
        return $value;
    }
}

class StubInterceptor implements InterceptorInterface
{
    public function intercept(ExecutionContextInterface $context, callable $next): mixed
    {
        return $next($context);
    }
}

class StubFilter implements ExceptionFilterInterface
{
    public function catch(\Throwable $exception, ExecutionContextInterface $context): mixed
    {
        return null;
    }
}

class MethodGuard implements GuardInterface
{
    public function canActivate(ExecutionContextInterface $context): bool
    {
        return false;
    }
}

class MethodInterceptor implements InterceptorInterface
{
    public function intercept(ExecutionContextInterface $context, callable $next): mixed
    {
        return $next($context);
    }
}

#[UseGuards([StubGuard::class])]
#[UseInterceptors([StubInterceptor::class])]
class FixtureController
{
    #[UsePipes([StubPipe::class])]
    #[UseFilters([StubFilter::class])]
    public function index(): string
    {
        return 'hello';
    }

    public function noAttributes(): string
    {
        return 'bare';
    }

    #[UseGuards([MethodGuard::class])]
    #[UseInterceptors([MethodInterceptor::class])]
    public function withMethodOverrides(): string
    {
        return 'overridden';
    }
}

class NoAttributeController
{
    public function handle(): string
    {
        return 'nothing';
    }
}
