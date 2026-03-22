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
        public bool $used = false,
    ) {}
}
