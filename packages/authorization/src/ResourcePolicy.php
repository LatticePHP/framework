<?php

declare(strict_types=1);

namespace Lattice\Authorization;

use Lattice\Contracts\Auth\PolicyInterface;
use Lattice\Contracts\Context\PrincipalInterface;

abstract class ResourcePolicy implements PolicyInterface
{
    abstract public function can(PrincipalInterface $principal, string $ability, mixed $subject = null): bool;
}
