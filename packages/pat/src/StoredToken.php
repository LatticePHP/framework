<?php

declare(strict_types=1);

namespace Lattice\Pat;

final readonly class StoredToken
{
    public function __construct(
        public string $id,
        public string $hash,
        public string|int $principalId,
        public string $name,
        public array $scopes,
        public ?\DateTimeImmutable $expiresAt,
        public \DateTimeImmutable $createdAt,
    ) {}
}
