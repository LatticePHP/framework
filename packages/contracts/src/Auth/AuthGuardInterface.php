<?php

declare(strict_types=1);

namespace Lattice\Contracts\Auth;

use Lattice\Contracts\Context\PrincipalInterface;

interface AuthGuardInterface
{
    public function authenticate(mixed $credentials): ?PrincipalInterface;

    public function supports(string $type): bool;
}
