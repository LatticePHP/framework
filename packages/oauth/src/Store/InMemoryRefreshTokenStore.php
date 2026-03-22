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
        string $familyId = '',
    ): void {
        $this->tokens[$token] = new StoredRefreshToken(
            token: $token,
            clientId: $clientId,
            userId: $userId,
            scopes: $scopes,
            expiresAt: $expiresAt,
            familyId: $familyId,
        );
    }

    public function find(string $token): ?StoredRefreshToken
    {
        return $this->tokens[$token] ?? null;
    }

    public function revoke(string $token): void
    {
        if (isset($this->tokens[$token])) {
            $stored = $this->tokens[$token];
            $this->tokens[$token] = new StoredRefreshToken(
                token: $stored->token,
                clientId: $stored->clientId,
                userId: $stored->userId,
                scopes: $stored->scopes,
                expiresAt: $stored->expiresAt,
                familyId: $stored->familyId,
                revoked: true,
            );
        }
    }

    public function revokeFamily(string $familyId): void
    {
        if ($familyId === '') {
            return;
        }

        foreach ($this->tokens as $key => $stored) {
            if ($stored->familyId === $familyId) {
                $this->tokens[$key] = new StoredRefreshToken(
                    token: $stored->token,
                    clientId: $stored->clientId,
                    userId: $stored->userId,
                    scopes: $stored->scopes,
                    expiresAt: $stored->expiresAt,
                    familyId: $stored->familyId,
                    revoked: true,
                );
            }
        }
    }
}
