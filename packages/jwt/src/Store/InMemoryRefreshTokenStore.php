<?php

declare(strict_types=1);

namespace Lattice\Jwt\Store;

use Lattice\Jwt\RefreshTokenRecord;
use Lattice\Jwt\RefreshTokenStoreInterface;

final class InMemoryRefreshTokenStore implements RefreshTokenStoreInterface
{
    /** @var array<string, RefreshTokenRecord> */
    private array $tokens = [];

    public function store(string $tokenHash, string $principalId, \DateTimeImmutable $expiresAt): void
    {
        $this->tokens[$tokenHash] = new RefreshTokenRecord(
            tokenHash: $tokenHash,
            principalId: $principalId,
            expiresAt: $expiresAt,
        );
    }

    public function find(string $tokenHash): ?RefreshTokenRecord
    {
        return $this->tokens[$tokenHash] ?? null;
    }

    public function revoke(string $tokenHash): void
    {
        if (isset($this->tokens[$tokenHash])) {
            $this->tokens[$tokenHash] = $this->tokens[$tokenHash]->withRevokedAt(new \DateTimeImmutable());
        }
    }

    public function revokeAllForPrincipal(string $principalId): void
    {
        foreach ($this->tokens as $hash => $record) {
            if ($record->principalId === $principalId && $record->revokedAt === null) {
                $this->tokens[$hash] = $record->withRevokedAt(new \DateTimeImmutable());
            }
        }
    }
}
