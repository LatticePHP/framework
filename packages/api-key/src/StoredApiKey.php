<?php

declare(strict_types=1);

namespace Lattice\ApiKey;

final readonly class StoredApiKey
{
    public function __construct(
        public string $id,
        public string $hash,
        public string $name,
        public array $scopes,
        public ?array $metadata,
        public \DateTimeImmutable $createdAt,
    ) {}
}
