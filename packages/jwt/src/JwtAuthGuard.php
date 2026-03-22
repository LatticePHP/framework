<?php

declare(strict_types=1);

namespace Lattice\Jwt;

use Lattice\Auth\Principal;
use Lattice\Contracts\Auth\AuthGuardInterface;
use Lattice\Contracts\Context\PrincipalInterface;

final class JwtAuthGuard implements AuthGuardInterface
{
    public function __construct(
        private readonly JwtEncoder $encoder,
        private readonly JwtConfig $config,
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

        try {
            $payload = $this->encoder->decode($token, $this->config->secret, $this->config->algorithm);
        } catch (\Throwable) {
            return null;
        }

        return new Principal(
            id: $payload['sub'] ?? '',
            scopes: $payload['scopes'] ?? [],
            roles: $payload['roles'] ?? [],
            claims: $payload,
        );
    }

    public function supports(string $type): bool
    {
        return $type === 'jwt';
    }
}
