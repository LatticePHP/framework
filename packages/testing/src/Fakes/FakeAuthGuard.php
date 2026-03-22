<?php

declare(strict_types=1);

namespace Lattice\Testing\Fakes;

use Lattice\Contracts\Auth\AuthGuardInterface;
use Lattice\Contracts\Context\PrincipalInterface;

/**
 * Returns a configured principal for all authentication checks.
 * Useful in tests where you want to bypass real authentication.
 */
final class FakeAuthGuard implements AuthGuardInterface
{
    public function __construct(
        private readonly ?PrincipalInterface $principal = null,
    ) {}

    public function authenticate(mixed $credentials): ?PrincipalInterface
    {
        return $this->principal;
    }

    public function supports(string $type): bool
    {
        return true;
    }
}
