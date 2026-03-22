<?php

declare(strict_types=1);

namespace Lattice\OAuth\Store;

use Lattice\OAuth\AuthorizationCode;
use Lattice\OAuth\AuthorizationCodeStoreInterface;

final class InMemoryAuthorizationCodeStore implements AuthorizationCodeStoreInterface
{
    /** @var array<string, AuthorizationCode> */
    private array $codes = [];

    public function store(
        string $code,
        string $clientId,
        string|int $userId,
        array $scopes,
        \DateTimeImmutable $expiresAt,
    ): void {
        $this->codes[$code] = new AuthorizationCode(
            code: $code,
            clientId: $clientId,
            userId: $userId,
            scopes: $scopes,
            expiresAt: $expiresAt,
        );
    }

    public function find(string $code): ?AuthorizationCode
    {
        return $this->codes[$code] ?? null;
    }

    public function revoke(string $code): void
    {
        unset($this->codes[$code]);
    }
}
