<?php

declare(strict_types=1);

namespace Lattice\Contracts\Pipeline;

use Lattice\Contracts\Context\ExecutionContextInterface;

interface ExceptionFilterInterface
{
    public function catch(\Throwable $exception, ExecutionContextInterface $context): mixed;
}
