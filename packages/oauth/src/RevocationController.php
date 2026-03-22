<?php

declare(strict_types=1);

namespace Lattice\OAuth;

/**
 * Handles the POST /oauth/revoke endpoint per RFC 7009.
 *
 * Per the spec, the endpoint always returns 200 even if the token was already invalid.
 */
final class RevocationController
{
    public function __construct(
        private readonly AccessTokenStoreInterface $accessTokenStore,
        private readonly RefreshTokenStoreInterface $refreshTokenStore,
    ) {}

    /**
     * Revoke a token per RFC 7009.
     *
     * Always returns true per the RFC, even if the token was already revoked or invalid.
     *
     * @param array<string, string> $params POST parameters containing 'token' and optional 'token_type_hint'
     */
    public function revoke(array $params): bool
    {
        $token = $params['token'] ?? null;
        if ($token === null) {
            return true;
        }

        $typeHint = $params['token_type_hint'] ?? null;

        if ($typeHint === 'refresh_token') {
            $this->revokeRefreshToken($token);

            return true;
        }

        if ($typeHint === 'access_token') {
            $this->revokeAccessToken($token);

            return true;
        }

        // No hint: try both
        $this->revokeAccessToken($token);
        $this->revokeRefreshToken($token);

        return true;
    }

    private function revokeAccessToken(string $token): void
    {
        $this->accessTokenStore->revoke($token);
    }

    private function revokeRefreshToken(string $token): void
    {
        $stored = $this->refreshTokenStore->find($token);
        if ($stored === null) {
            return;
        }

        // Revoke the refresh token
        $this->refreshTokenStore->revoke($token);

        // Cascade: revoke all access tokens in the same family
        if ($stored->familyId !== '') {
            $this->accessTokenStore->revokeByFamily($stored->familyId);
        }
    }
}
