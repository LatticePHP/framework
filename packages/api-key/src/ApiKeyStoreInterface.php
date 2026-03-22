<?php

declare(strict_types=1);

namespace Lattice\ApiKey;

interface ApiKeyStoreInterface
{
    public function store(
        string $id,
        string $hash,
        string $name,
        array $scopes,
        ?array $metadata,
    ): void;

    public function findByHash(string $hash): ?StoredApiKey;

    public function delete(string $id): void;

    /** @return array<StoredApiKey> */
    public function all(): array;
}
