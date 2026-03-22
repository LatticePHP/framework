<?php

declare(strict_types=1);

namespace Lattice\OAuth;

use Lattice\OAuth\Grant\GrantHandlerInterface;

/**
 * Handles the POST /oauth/token endpoint.
 *
 * Delegates to the OAuthServer for token exchange.
 * This is a thin controller that parses the request and returns a structured response.
 */
final class TokenController
{
    public function __construct(
        private readonly OAuthServer $server,
    ) {}

    /**
     * Handle a token request.
     *
     * @param array<string, string> $params POST parameters from /oauth/token
     * @return array{access_token: string, token_type: string, expires_in: int, refresh_token?: string, scope?: string}
     */
    public function handleTokenRequest(array $params): array
    {
        $response = $this->server->handleTokenRequest($params);

        $result = [
            'access_token' => $response->accessToken,
            'token_type' => $response->tokenType,
            'expires_in' => $response->expiresIn,
        ];

        if ($response->refreshToken !== null) {
            $result['refresh_token'] = $response->refreshToken;
        }

        if ($response->scope !== null && $response->scope !== '') {
            $result['scope'] = $response->scope;
        }

        return $result;
    }
}
