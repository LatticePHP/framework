<?php

declare(strict_types=1);

namespace Lattice\Contracts\Pipeline;

use Lattice\Contracts\Context\ExecutionContextInterface;

interface InterceptorInterface
{
    public function intercept(ExecutionContextInterface $context, callable $next): mixed;
}
