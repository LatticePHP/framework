<?php

declare(strict_types=1);

namespace Lattice\OAuth;

interface AccessTokenStoreInterface
{
    public function store(StoredAccessToken $token): void;

    public function find(string $token): ?StoredAccessToken;

    public function revoke(string $token): void;

    /**
     * Revoke all access tokens associated with a refresh token family.
     */
    public function revokeByFamily(string $familyId): void;
}
