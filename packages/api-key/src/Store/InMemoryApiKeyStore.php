<?php

declare(strict_types=1);

namespace Lattice\ApiKey\Store;

use Lattice\ApiKey\ApiKeyStoreInterface;
use Lattice\ApiKey\StoredApiKey;

final class InMemoryApiKeyStore implements ApiKeyStoreInterface
{
    /** @var array<string, StoredApiKey> keyed by key ID */
    private array $keys = [];

    /** @var array<string, string> hash => key ID index */
    private array $hashIndex = [];

    public function store(
        string $id,
        string $hash,
        string $name,
        array $scopes,
        ?array $metadata,
    ): void {
        $key = new StoredApiKey(
            id: $id,
            hash: $hash,
            name: $name,
            scopes: $scopes,
            metadata: $metadata,
            createdAt: new \DateTimeImmutable(),
        );

        $this->keys[$id] = $key;
        $this->hashIndex[$hash] = $id;
    }

    public function findByHash(string $hash): ?StoredApiKey
    {
        $id = $this->hashIndex[$hash] ?? null;

        if ($id === null) {
            return null;
        }

        return $this->keys[$id] ?? null;
    }

    public function delete(string $id): void
    {
        if (isset($this->keys[$id])) {
            unset($this->hashIndex[$this->keys[$id]->hash]);
            unset($this->keys[$id]);
        }
    }

    /** @return array<StoredApiKey> */
    public function all(): array
    {
        return array_values($this->keys);
    }
}
