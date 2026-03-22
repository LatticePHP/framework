<?php

declare(strict_types=1);

namespace Lattice\Pat;

use Lattice\Auth\Principal;
use Lattice\Contracts\Context\PrincipalInterface;

final class PatManager
{
    public function __construct(
        private readonly PatStoreInterface $store,
    ) {}

    public function create(
        PrincipalInterface $principal,
        string $name,
        array $scopes = [],
        ?\DateTimeInterface $expiresAt = null,
    ): CreateTokenResult {
        $tokenId = bin2hex(random_bytes(16));
        $plainToken = bin2hex(random_bytes(32));
        $hash = hash('sha256', $plainToken);

        $expiresAtImmutable = $expiresAt !== null
            ? \DateTimeImmutable::createFromInterface($expiresAt)
            : null;

        $this->store->store(
            id: $tokenId,
            hash: $hash,
            principalId: $principal->getId(),
            name: $name,
            scopes: $scopes,
            expiresAt: $expiresAtImmutable,
        );

        return new CreateTokenResult(
            tokenId: $tokenId,
            plainToken: $plainToken,
            name: $name,
            scopes: $scopes,
            expiresAt: $expiresAtImmutable,
        );
    }

    public function validate(string $plainToken): ?PrincipalInterface
    {
        $hash = hash('sha256', $plainToken);
        $stored = $this->store->findByHash($hash);

        if ($stored === null) {
            return null;
        }

        if ($stored->expiresAt !== null && $stored->expiresAt < new \DateTimeImmutable()) {
            return null;
        }

        return new Principal(
            id: $stored->principalId,
            type: 'pat',
            scopes: $stored->scopes,
        );
    }

    public function revoke(string $tokenId): void
    {
        $this->store->delete($tokenId);
    }

    public function revokeAllForPrincipal(string|int $principalId): void
    {
        $this->store->deleteAllForPrincipal($principalId);
    }
}
