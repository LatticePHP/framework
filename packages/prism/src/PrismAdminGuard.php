<?php

declare(strict_types=1);

namespace Lattice\Prism;

use Lattice\Contracts\Context\ExecutionContextInterface;
use Lattice\Contracts\Pipeline\GuardInterface;

final class PrismAdminGuard implements GuardInterface
{
    /**
     * @param list<string> $allowedRoles
     */
    public function __construct(
        private readonly bool $enabled = true,
        private readonly array $allowedRoles = ['admin'],
    ) {}

    public function canActivate(ExecutionContextInterface $context): bool
    {
        if (!$this->enabled) {
            return true;
        }

        $principal = $context->getPrincipal();

        if ($principal === null) {
            return false;
        }

        // Check if the principal has any of the allowed roles
        $principalRoles = $principal->getRoles();

        foreach ($this->allowedRoles as $role) {
            if (in_array($role, $principalRoles, true)) {
                return true;
            }
        }

        return false;
    }
}
