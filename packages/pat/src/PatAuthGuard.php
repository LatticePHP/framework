<?php

declare(strict_types=1);

namespace Lattice\Pat;

use Lattice\Contracts\Auth\AuthGuardInterface;
use Lattice\Contracts\Context\PrincipalInterface;

final class PatAuthGuard implements AuthGuardInterface
{
    public function __construct(
        private readonly PatManager $manager,
    ) {}

    public function authenticate(mixed $credentials): ?PrincipalInterface
    {
        if (!is_array($credentials)) {
            return null;
        }

        $authorization = $credentials['authorization'] ?? null;

        if ($authorization === null || !is_string($authorization)) {
            return null;
        }

        if (!str_starts_with($authorization, 'Bearer ')) {
            return null;
        }

        $token = substr($authorization, 7);

        return $this->manager->validate($token);
    }

    public function supports(string $type): bool
    {
        return $type === 'pat';
    }
}
