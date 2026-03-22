<?php

declare(strict_types=1);

namespace Lattice\Auth;

use Lattice\Contracts\Context\PrincipalInterface;

final class AuthorizationChecker
{
    /** @var array<string, callable(PrincipalInterface, mixed): bool> */
    private array $abilities = [];

    public function registerAbility(string $ability, callable $callback): void
    {
        $this->abilities[$ability] = $callback;
    }

    public function checkAbility(PrincipalInterface $principal, string $ability, mixed $subject = null): bool
    {
        if (!isset($this->abilities[$ability])) {
            return false;
        }

        return ($this->abilities[$ability])($principal, $subject);
    }

    /** @param array<string> $requiredScopes */
    public function checkScopes(PrincipalInterface $principal, array $requiredScopes): bool
    {
        foreach ($requiredScopes as $scope) {
            if (!$principal->hasScope($scope)) {
                return false;
            }
        }

        return true;
    }

    /** @param array<string> $requiredRoles */
    public function checkRoles(PrincipalInterface $principal, array $requiredRoles): bool
    {
        foreach ($requiredRoles as $role) {
            if (!$principal->hasRole($role)) {
                return false;
            }
        }

        return true;
    }
}
