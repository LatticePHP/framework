<?php

declare(strict_types=1);

namespace Lattice\Jwt;

interface RefreshTokenStoreInterface
{
    public function store(string $tokenHash, string $principalId, \DateTimeImmutable $expiresAt): void;

    public function find(string $tokenHash): ?RefreshTokenRecord;

    public function revoke(string $tokenHash): void;

    public function revokeAllForPrincipal(string $principalId): void;
}
