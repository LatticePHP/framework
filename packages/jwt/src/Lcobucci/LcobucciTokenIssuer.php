<?php

declare(strict_types=1);

namespace Lattice\Jwt\Lcobucci;

use Lattice\Contracts\Auth\TokenIssuerInterface;
use Lattice\Contracts\Auth\TokenPairInterface;
use Lattice\Contracts\Context\PrincipalInterface;
use Lattice\Jwt\Exception\InvalidTokenException;
use Lattice\Jwt\Exception\RevokedTokenException;
use Lattice\Jwt\JwtConfig;
use Lattice\Jwt\RefreshTokenStoreInterface;
use Lattice\Jwt\TokenPair;

/**
 * Production-recommended token issuer backed by lcobucci/jwt.
 *
 * Same interface as {@see \Lattice\Jwt\JwtTokenIssuer} but uses
 * {@see LcobucciJwtEncoder} for standards-compliant JWT handling,
 * asymmetric key support (RS256, ES256), and key rotation.
 */
final class LcobucciTokenIssuer implements TokenIssuerInterface
{
    private readonly LcobucciJwtEncoder $encoder;

    public function __construct(
        private readonly RefreshTokenStoreInterface $refreshTokenStore,
        private readonly JwtConfig $config,
        ?LcobucciJwtEncoder $encoder = null,
    ) {
        $this->encoder = $encoder ?? new LcobucciJwtEncoder(
            secret: $this->config->secret,
            algorithm: $this->config->algorithm,
        );
    }

    public function issueAccessToken(PrincipalInterface $principal, array $scopes = []): TokenPairInterface
    {
        $claims = [
            'sub' => (string) $principal->getId(),
            'iss' => $this->config->issuer,
            'aud' => $this->config->audience,
            'scopes' => $scopes,
            'roles' => $principal->getRoles(),
        ];

        $accessToken = $this->encoder->encode($claims, $this->config->accessTokenTtl);
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

    /**
     * Access the underlying encoder for advanced configuration.
     */
    public function getEncoder(): LcobucciJwtEncoder
    {
        return $this->encoder;
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
