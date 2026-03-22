<?php

declare(strict_types=1);

namespace Lattice\Auth;

use Lattice\Contracts\Auth\AuthGuardInterface;
use Lattice\Contracts\Context\PrincipalInterface;

final class AuthManager
{
    /** @var array<string, AuthGuardInterface> */
    private array $guards = [];

    public function __construct(
        private readonly string $defaultGuard = 'default',
    ) {}

    public function registerGuard(string $name, AuthGuardInterface $guard): void
    {
        $this->guards[$name] = $guard;
    }

    public function authenticate(string $guardName, mixed $credentials): ?PrincipalInterface
    {
        if (!isset($this->guards[$guardName])) {
            throw new \InvalidArgumentException("Unknown auth guard: {$guardName}");
        }

        return $this->guards[$guardName]->authenticate($credentials);
    }

    public function getDefaultGuard(): string
    {
        return $this->defaultGuard;
    }
}
