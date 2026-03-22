<?php

declare(strict_types=1);

namespace Lattice\OAuth\Store;

use Lattice\OAuth\RefreshTokenStoreInterface;
use Lattice\OAuth\StoredRefreshToken;

final class InMemoryRefreshTokenStore implements RefreshTokenStoreInterface
{
    /** @var array<string, StoredRefreshToken> */
    private array $tokens = [];

    public function store(
        string $token,
        string $clientId,
        string|int $userId,
        array $scopes,
        \DateTimeImmutable $expiresAt,
    ): void {
        $this->tokens[$token] = new StoredRefreshToken(
            token: $token,
            clientId: $clientId,
            userId: $userId,
            scopes: $scopes,
            expiresAt: $expiresAt,
        );
    }

    public function find(string $token): ?StoredRefreshToken
    {
        return $this->tokens[$token] ?? null;
    }

    public function revoke(string $token): void
    {
        unset($this->tokens[$token]);
    }
}
