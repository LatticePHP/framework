<?php

declare(strict_types=1);

namespace Lattice\Prism\Database;

final class Project
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $apiKeyHash,
        public readonly string $createdAt,
        public readonly ?string $slug = null,
        public readonly ?string $updatedAt = null,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (string) $data['id'],
            name: (string) $data['name'],
            apiKeyHash: (string) ($data['api_key_hash'] ?? ''),
            createdAt: (string) ($data['created_at'] ?? date('c')),
            slug: isset($data['slug']) ? (string) $data['slug'] : null,
            updatedAt: isset($data['updated_at']) ? (string) $data['updated_at'] : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'api_key_hash' => $this->apiKeyHash,
            'slug' => $this->slug,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }

    /**
     * Verify a raw API key against this project's stored hash.
     */
    public function verifyApiKey(string $rawKey): bool
    {
        return hash('sha256', $rawKey) === $this->apiKeyHash;
    }

    /**
     * Hash a raw API key for storage.
     */
    public static function hashApiKey(string $rawKey): string
    {
        return hash('sha256', $rawKey);
    }
}
