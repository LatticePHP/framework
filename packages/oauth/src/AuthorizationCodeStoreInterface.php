<?php

declare(strict_types=1);

namespace Lattice\OAuth;

interface AuthorizationCodeStoreInterface
{
    public function store(
        string $code,
        string $clientId,
        string|int $userId,
        array $scopes,
        \DateTimeImmutable $expiresAt,
    ): void;

    public function find(string $code): ?AuthorizationCode;

    public function revoke(string $code): void;
}
