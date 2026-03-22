<?php

declare(strict_types=1);

namespace Lattice\Contracts\Auth;

use Lattice\Contracts\Context\PrincipalInterface;

interface PolicyInterface
{
    public function can(PrincipalInterface $principal, string $ability, mixed $subject = null): bool;
}
