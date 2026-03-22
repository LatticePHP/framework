<?php

declare(strict_types=1);

namespace Lattice\Auth\Guard;

use Lattice\Auth\AuthManager;
use Lattice\Contracts\Context\ExecutionContextInterface;
use Lattice\Contracts\Pipeline\GuardInterface;

final class AuthGuard implements GuardInterface
{
    public function __construct(
        private readonly AuthManager $authManager,
    ) {}

    public function canActivate(ExecutionContextInterface $context): bool
    {
        return $context->getPrincipal() !== null;
    }
}
