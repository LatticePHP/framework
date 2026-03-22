<?php

declare(strict_types=1);

namespace Lattice\OAuth\Grant;

use Lattice\OAuth\ClientInterface;
use Lattice\OAuth\RefreshTokenStoreInterface;
use Lattice\OAuth\TokenResponse;

final class RefreshTokenGrant implements GrantHandlerInterface
{
    public function __construct(
        private readonly RefreshTokenStoreInterface $refreshTokenStore,
        private readonly string $secret,
        private readonly int $accessTokenTtl = 3600,
        private readonly int $refreshTokenTtl = 86400,
    ) {}

    public function supports(string $grantType): bool
    {
        return $grantType === 'refresh_token';
    }

    public function handle(array $params, ClientInterface $client): TokenResponse
    {
        $refreshToken = $params['refresh_token'] ?? throw new \InvalidArgumentException('Missing refresh token');

        $stored = $this->refreshTokenStore->find($refreshToken);

        if ($stored === null) {
            throw new \InvalidArgumentException('Invalid refresh token');
        }

        // Detect reuse of a revoked token: revoke entire family
        if ($stored->revoked) {
            if ($stored->familyId !== '') {
                $this->refreshTokenStore->revokeFamily($stored->familyId);
            }

            throw new \InvalidArgumentException('Refresh token has been revoked (possible reuse detected)');
        }

        if ($stored->expiresAt < new \DateTimeImmutable()) {
            throw new \InvalidArgumentException('Refresh token expired');
        }

        if ($stored->clientId !== $client->getId()) {
            throw new \InvalidArgumentException('Client mismatch');
        }

        // Revoke old refresh token (rotation)
        $this->refreshTokenStore->revoke($refreshToken);

        // Issue new tokens in the same family
        $scopes = $stored->scopes;
        $accessToken = $this->generateAccessToken($stored->userId, $scopes);
        $newRefreshToken = bin2hex(random_bytes(32));

        $this->refreshTokenStore->store(
            token: $newRefreshToken,
            clientId: $client->getId(),
            userId: $stored->userId,
            scopes: $scopes,
            expiresAt: new \DateTimeImmutable("+{$this->refreshTokenTtl} seconds"),
            familyId: $stored->familyId,
        );

        return new TokenResponse(
            accessToken: $accessToken,
            tokenType: 'Bearer',
            expiresIn: $this->accessTokenTtl,
            refreshToken: $newRefreshToken,
            scope: implode(' ', $scopes),
        );
    }

    /**
     * @param array<string> $scopes
     */
    private function generateAccessToken(string|int $userId, array $scopes): string
    {
        $payload = [
            'sub' => (string) $userId,
            'type' => 'refresh_token',
            'scopes' => $scopes,
            'iat' => time(),
            'exp' => time() + $this->accessTokenTtl,
        ];

        $header = base64_encode(json_encode(['typ' => 'JWT', 'alg' => 'HS256']));
        $body = base64_encode(json_encode($payload));
        $signature = base64_encode(hash_hmac('sha256', "{$header}.{$body}", $this->secret, true));

        return "{$header}.{$body}.{$signature}";
    }
}
