<?php

declare(strict_types=1);

namespace Lattice\Pat\Store;

use Lattice\Pat\PatStoreInterface;
use Lattice\Pat\StoredToken;

final class InMemoryPatStore implements PatStoreInterface
{
    /** @var array<string, StoredToken> keyed by token ID */
    private array $tokens = [];

    /** @var array<string, string> hash => token ID index */
    private array $hashIndex = [];

    public function store(
        string $id,
        string $hash,
        string|int $principalId,
        string $name,
        array $scopes,
        ?\DateTimeImmutable $expiresAt,
    ): void {
        $token = new StoredToken(
            id: $id,
            hash: $hash,
            principalId: $principalId,
            name: $name,
            scopes: $scopes,
            expiresAt: $expiresAt,
            createdAt: new \DateTimeImmutable(),
        );

        $this->tokens[$id] = $token;
        $this->hashIndex[$hash] = $id;
    }

    public function findByHash(string $hash): ?StoredToken
    {
        $id = $this->hashIndex[$hash] ?? null;

        if ($id === null) {
            return null;
        }

        return $this->tokens[$id] ?? null;
    }

    public function delete(string $id): void
    {
        if (isset($this->tokens[$id])) {
            unset($this->hashIndex[$this->tokens[$id]->hash]);
            unset($this->tokens[$id]);
        }
    }

    public function deleteAllForPrincipal(string|int $principalId): void
    {
        foreach ($this->tokens as $id => $token) {
            if ($token->principalId === $principalId) {
                unset($this->hashIndex[$token->hash]);
                unset($this->tokens[$id]);
            }
        }
    }
}
