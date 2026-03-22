<?php

declare(strict_types=1);

namespace Lattice\Jwt;

final class JwtConfig
{
    public function __construct(
        public readonly string $secret,
        public readonly string $algorithm = 'HS256',
        public readonly int $accessTokenTtl = 3600,
        public readonly int $refreshTokenTtl = 86400,
        public readonly string $issuer = 'lattice',
        public readonly string $audience = 'api',
    ) {}
}
