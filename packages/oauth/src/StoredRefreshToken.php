<?php

declare(strict_types=1);

namespace Lattice\OAuth;

final class StoredRefreshToken
{
    public function __construct(
        public readonly string $token,
        public readonly string $clientId,
        public readonly string|int $userId,
        public readonly array $scopes,
        public readonly \DateTimeImmutable $expiresAt,
        public readonly string $familyId = '',
        public bool $revoked = false,
    ) {}

    public function isExpired(): bool
    {
        return $this->expiresAt < new \DateTimeImmutable();
    }
}
