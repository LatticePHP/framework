<?php

declare(strict_types=1);

namespace Lattice\OAuth;

final class StoredAccessToken
{
    public function __construct(
        public readonly string $token,
        public readonly string $clientId,
        public readonly string|int $userId,
        public readonly array $scopes,
        public readonly \DateTimeImmutable $issuedAt,
        public readonly \DateTimeImmutable $expiresAt,
        public readonly ?string $refreshTokenFamily = null,
        public bool $revoked = false,
    ) {}

    public function isExpired(): bool
    {
        return $this->expiresAt < new \DateTimeImmutable();
    }

    public function isActive(): bool
    {
        return !$this->revoked && !$this->isExpired();
    }
}
