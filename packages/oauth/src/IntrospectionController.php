<?php

declare(strict_types=1);

namespace Lattice\OAuth;

/**
 * Handles the POST /oauth/introspect endpoint per RFC 7662.
 */
final class IntrospectionController
{
    public function __construct(
        private readonly AccessTokenStoreInterface $accessTokenStore,
        private readonly RefreshTokenStoreInterface $refreshTokenStore,
    ) {}

    /**
     * Introspect a token per RFC 7662.
     *
     * @param array<string, string> $params POST parameters containing 'token' and optional 'token_type_hint'
     * @return array<string, mixed> RFC 7662 introspection response
     */
    public function introspect(array $params): array
    {
        $token = $params['token'] ?? throw new \InvalidArgumentException('Missing token parameter');
        $typeHint = $params['token_type_hint'] ?? null;

        // Try to find the token based on the hint
        if ($typeHint === 'refresh_token') {
            return $this->introspectRefreshToken($token);
        }

        if ($typeHint === 'access_token' || $typeHint === null) {
            $result = $this->introspectAccessToken($token);
            if ($result['active'] || $typeHint === 'access_token') {
                return $result;
            }

            // If no hint and not found as access token, try refresh token
            return $this->introspectRefreshToken($token);
        }

        return ['active' => false];
    }

    /**
     * @return array<string, mixed>
     */
    private function introspectAccessToken(string $token): array
    {
        $stored = $this->accessTokenStore->find($token);

        if ($stored === null) {
            return ['active' => false];
        }

        if (!$stored->isActive()) {
            return ['active' => false];
        }

        return [
            'active' => true,
            'scope' => implode(' ', $stored->scopes),
            'client_id' => $stored->clientId,
            'username' => (string) $stored->userId,
            'token_type' => 'Bearer',
            'exp' => $stored->expiresAt->getTimestamp(),
            'iat' => $stored->issuedAt->getTimestamp(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function introspectRefreshToken(string $token): array
    {
        $stored = $this->refreshTokenStore->find($token);

        if ($stored === null || $stored->revoked || $stored->isExpired()) {
            return ['active' => false];
        }

        return [
            'active' => true,
            'scope' => implode(' ', $stored->scopes),
            'client_id' => $stored->clientId,
            'username' => (string) $stored->userId,
            'token_type' => 'refresh_token',
            'exp' => $stored->expiresAt->getTimestamp(),
        ];
    }
}
