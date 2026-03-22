<?php

declare(strict_types=1);

namespace Lattice\OAuth;

final readonly class StoredRefreshToken
{
    public function __construct(
        public string $token,
        public string $clientId,
        public string|int $userId,
        public array $scopes,
        public \DateTimeImmutable $expiresAt,
    ) {}
}
