<?php

declare(strict_types=1);

namespace Lattice\OAuth;

final class AuthorizationCode
{
    public function __construct(
        public readonly string $code,
        public readonly string $clientId,
        public readonly string|int $userId,
        public readonly array $scopes,
        public readonly \DateTimeImmutable $expiresAt,
        public readonly ?string $redirectUri = null,
        public readonly ?string $codeChallenge = null,
        public readonly ?string $codeChallengeMethod = null,
        public bool $used = false,
    ) {}

    public function isExpired(): bool
    {
        return $this->expiresAt < new \DateTimeImmutable();
    }

    public function hasPkce(): bool
    {
        return $this->codeChallenge !== null;
    }
}
