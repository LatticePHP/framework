<?php

declare(strict_types=1);

namespace Lattice\Jwt;

use Lattice\Contracts\Auth\TokenPairInterface;

final class TokenPair implements TokenPairInterface
{
    public function __construct(
        private readonly string $accessToken,
        private readonly string $refreshToken,
        private readonly int $expiresIn,
        private readonly string $tokenType = 'Bearer',
    ) {}

    public function getAccessToken(): string
    {
        return $this->accessToken;
    }

    public function getRefreshToken(): string
    {
        return $this->refreshToken;
    }

    public function getExpiresIn(): int
    {
        return $this->expiresIn;
    }

    public function getTokenType(): string
    {
        return $this->tokenType;
    }
}
