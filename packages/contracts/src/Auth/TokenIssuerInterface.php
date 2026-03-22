<?php

declare(strict_types=1);

namespace Lattice\Contracts\Auth;

use Lattice\Contracts\Context\PrincipalInterface;

interface TokenIssuerInterface
{
    public function issueAccessToken(PrincipalInterface $principal, array $scopes = []): TokenPairInterface;

    public function refreshAccessToken(string $refreshToken): TokenPairInterface;

    public function revokeRefreshToken(string $refreshToken): void;
}
