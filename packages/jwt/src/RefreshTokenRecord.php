<?php

declare(strict_types=1);

namespace Lattice\Jwt;

final class RefreshTokenRecord
{
    public function __construct(
        public readonly string $tokenHash,
        public readonly string $principalId,
        public readonly \DateTimeImmutable $expiresAt,
        public readonly ?\DateTimeImmutable $revokedAt = null,
    ) {}

    public function isRevoked(): bool
    {
        return $this->revokedAt !== null;
    }

    public function isExpired(): bool
    {
        return $this->expiresAt < new \DateTimeImmutable();
    }

    public function withRevokedAt(\DateTimeImmutable $revokedAt): self
    {
        return new self(
            tokenHash: $this->tokenHash,
            principalId: $this->principalId,
            expiresAt: $this->expiresAt,
            revokedAt: $revokedAt,
        );
    }
}
