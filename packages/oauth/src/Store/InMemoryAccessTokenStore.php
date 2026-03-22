<?php

declare(strict_types=1);

namespace Lattice\OAuth\Store;

use Lattice\OAuth\AccessTokenStoreInterface;
use Lattice\OAuth\StoredAccessToken;

final class InMemoryAccessTokenStore implements AccessTokenStoreInterface
{
    /** @var array<string, StoredAccessToken> */
    private array $tokens = [];

    public function store(StoredAccessToken $token): void
    {
        $this->tokens[$token->token] = $token;
    }

    public function find(string $token): ?StoredAccessToken
    {
        return $this->tokens[$token] ?? null;
    }

    public function revoke(string $token): void
    {
        if (isset($this->tokens[$token])) {
            $stored = $this->tokens[$token];
            $this->tokens[$token] = new StoredAccessToken(
                token: $stored->token,
                clientId: $stored->clientId,
                userId: $stored->userId,
                scopes: $stored->scopes,
                issuedAt: $stored->issuedAt,
                expiresAt: $stored->expiresAt,
                refreshTokenFamily: $stored->refreshTokenFamily,
                revoked: true,
            );
        }
    }

    public function revokeByFamily(string $familyId): void
    {
        if ($familyId === '') {
            return;
        }

        foreach ($this->tokens as $key => $stored) {
            if ($stored->refreshTokenFamily === $familyId) {
                $this->tokens[$key] = new StoredAccessToken(
                    token: $stored->token,
                    clientId: $stored->clientId,
                    userId: $stored->userId,
                    scopes: $stored->scopes,
                    issuedAt: $stored->issuedAt,
                    expiresAt: $stored->expiresAt,
                    refreshTokenFamily: $stored->refreshTokenFamily,
                    revoked: true,
                );
            }
        }
    }
}
