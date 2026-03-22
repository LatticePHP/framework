<?php

declare(strict_types=1);

namespace Lattice\ApiKey;

use Lattice\Contracts\Auth\AuthGuardInterface;
use Lattice\Contracts\Context\PrincipalInterface;

final class ApiKeyAuthGuard implements AuthGuardInterface
{
    public function __construct(
        private readonly ApiKeyManager $manager,
    ) {}

    public function authenticate(mixed $credentials): ?PrincipalInterface
    {
        if (!is_array($credentials)) {
            return null;
        }

        // Prefer X-API-Key header over query param
        $key = $credentials['x-api-key'] ?? $credentials['api_key'] ?? null;

        if ($key === null || !is_string($key)) {
            return null;
        }

        return $this->manager->validate($key);
    }

    public function supports(string $type): bool
    {
        return $type === 'api-key';
    }
}
