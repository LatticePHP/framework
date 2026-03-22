<?php

declare(strict_types=1);

namespace Lattice\Pipeline\Guard;

use Lattice\Contracts\Context\ExecutionContextInterface;
use Lattice\Contracts\Pipeline\GuardInterface;
use Lattice\Pipeline\Exceptions\ForbiddenException;

final class GuardChain
{
    /**
     * Execute guards in sequence. All must return true.
     *
     * @param array<GuardInterface> $guards
     * @throws ForbiddenException
     */
    public function execute(array $guards, ExecutionContextInterface $context): bool
    {
        foreach ($guards as $guard) {
            if (!$guard->canActivate($context)) {
                throw new ForbiddenException();
            }
        }

        return true;
    }
}
