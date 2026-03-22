<?php

declare(strict_types=1);

namespace Lattice\Jwt;

use Lattice\Contracts\Auth\TokenIssuerInterface;
use Lattice\Contracts\Auth\TokenPairInterface;
use Lattice\Contracts\Context\PrincipalInterface;
use Lattice\Jwt\Exception\InvalidTokenException;
use Lattice\Jwt\Exception\RevokedTokenException;

final class JwtTokenIssuer implements TokenIssuerInterface
{
    public function __construct(
        private readonly JwtEncoder $encoder,
        private readonly RefreshTokenStoreInterface $refreshTokenStore,
        private readonly JwtConfig $config,
    ) {}

    public function issueAccessToken(PrincipalInterface $principal, array $scopes = []): TokenPairInterface
    {
        $now = time();

        $payload = [
            'sub' => (string) $principal->getId(),
            'iss' => $this->config->issuer,
            'aud' => $this->config->audience,
            'iat' => $now,
            'exp' => $now + $this->config->accessTokenTtl,
            'scopes' => $scopes,
            'roles' => $principal->getRoles(),
        ];

        $accessToken = $this->encoder->encode($payload, $this->config->secret, $this->config->algorithm);
        $refreshToken = $this->generateRefreshToken();
        $refreshTokenHash = $this->hashToken($refreshToken);

        $this->refreshTokenStore->store(
            $refreshTokenHash,
            (string) $principal->getId(),
            new \DateTimeImmutable("+{$this->config->refreshTokenTtl} seconds"),
        );

        return new TokenPair(
            accessToken: $accessToken,
            refreshToken: $refreshToken,
            expiresIn: $this->config->accessTokenTtl,
        );
    }

    public function refreshAccessToken(string $refreshToken): TokenPairInterface
    {
        $hash = $this->hashToken($refreshToken);
        $record = $this->refreshTokenStore->find($hash);

        if ($record === null) {
            throw new InvalidTokenException('Refresh token not found');
        }

        if ($record->isRevoked()) {
            throw new RevokedTokenException();
        }

        if ($record->isExpired()) {
            throw new InvalidTokenException('Refresh token has expired');
        }

        // Revoke the old refresh token (rotation)
        $this->refreshTokenStore->revoke($hash);

        // Issue new token pair with the principal's stored info
        // We reconstruct a minimal principal from the stored data
        $principal = new \Lattice\Auth\Principal(
            id: $record->principalId,
        );

        return $this->issueAccessToken($principal);
    }

    public function revokeRefreshToken(string $refreshToken): void
    {
        $hash = $this->hashToken($refreshToken);
        $this->refreshTokenStore->revoke($hash);
    }

    private function generateRefreshToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    private function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }
}
