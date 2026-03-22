<?php

declare(strict_types=1);

namespace Lattice\Pat;

interface PatStoreInterface
{
    public function store(
        string $id,
        string $hash,
        string|int $principalId,
        string $name,
        array $scopes,
        ?\DateTimeImmutable $expiresAt,
    ): void;

    public function findByHash(string $hash): ?StoredToken;

    public function delete(string $id): void;

    public function deleteAllForPrincipal(string|int $principalId): void;
}
