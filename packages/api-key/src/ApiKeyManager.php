<?php

declare(strict_types=1);

namespace Lattice\ApiKey;

final class ApiKeyManager
{
    public function __construct(
        private readonly ApiKeyStoreInterface $store,
        private readonly string $prefix = 'lk',
    ) {}

    public function create(string $name, array $scopes = [], ?array $metadata = null): CreateApiKeyResult
    {
        $keyId = bin2hex(random_bytes(16));
        $randomPart = bin2hex(random_bytes(24));
        $plainKey = $this->prefix . '_' . $randomPart;
        $hash = hash('sha256', $plainKey);

        $this->store->store(
            id: $keyId,
            hash: $hash,
            name: $name,
            scopes: $scopes,
            metadata: $metadata,
        );

        return new CreateApiKeyResult(
            keyId: $keyId,
            plainKey: $plainKey,
            name: $name,
            scopes: $scopes,
        );
    }

    public function validate(string $key): ?ApiKeyPrincipal
    {
        $hash = hash('sha256', $key);
        $stored = $this->store->findByHash($hash);

        if ($stored === null) {
            return null;
        }

        return new ApiKeyPrincipal(
            id: $stored->id,
            name: $stored->name,
            scopes: $stored->scopes,
            metadata: $stored->metadata ?? [],
        );
    }

    public function revoke(string $keyId): void
    {
        $this->store->delete($keyId);
    }

    /** @return array<StoredApiKey> */
    public function list(): array
    {
        return $this->store->all();
    }
}
