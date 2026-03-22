<?php

declare(strict_types=1);

namespace Lattice\OAuth;

final readonly class TokenResponse
{
    public function __construct(
        public string $accessToken,
        public string $tokenType,
        public int $expiresIn,
        public ?string $refreshToken,
        public ?string $scope,
    ) {}
}
