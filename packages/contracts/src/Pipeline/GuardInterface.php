<?php

declare(strict_types=1);

namespace Lattice\Contracts\Pipeline;

use Lattice\Contracts\Context\ExecutionContextInterface;

interface GuardInterface
{
    public function canActivate(ExecutionContextInterface $context): bool;
}
