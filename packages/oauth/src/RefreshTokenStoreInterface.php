<?php

declare(strict_types=1);

namespace Lattice\OAuth;

interface RefreshTokenStoreInterface
{
    public function store(
        string $token,
        string $clientId,
        string|int $userId,
        array $scopes,
        \DateTimeImmutable $expiresAt,
    ): void;

    public function find(string $token): ?StoredRefreshToken;

    public function revoke(string $token): void;
}
