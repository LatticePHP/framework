<?php

declare(strict_types=1);

namespace Lattice\OAuth;

/**
 * Handles the /oauth/authorize endpoint.
 *
 * Validates the client, redirect_uri, scopes, PKCE challenge, and generates
 * an authorization code upon user consent.
 */
final class AuthorizationController
{
    public function __construct(
        private readonly ClientStoreInterface $clientStore,
        private readonly AuthorizationCodeStoreInterface $codeStore,
        private readonly ScopeRegistry $scopeRegistry,
        private readonly int $codeTtl = 600,
    ) {}

    /**
     * Validates an authorization request and returns the data needed
     * to display a consent screen.
     *
     * @param array<string, string> $params Query parameters from GET /oauth/authorize
     * @return array{client: ClientInterface, scopes: array<string>, state: ?string, redirect_uri: string}
     */
    public function validateAuthorizationRequest(array $params): array
    {
        $responseType = $params['response_type'] ?? throw new \InvalidArgumentException('Missing response_type');
        if ($responseType !== 'code') {
            throw new \InvalidArgumentException("Unsupported response_type: {$responseType}");
        }

        $clientId = $params['client_id'] ?? throw new \InvalidArgumentException('Missing client_id');
        $client = $this->clientStore->find($clientId);
        if ($client === null) {
            throw new \InvalidArgumentException('Unknown client');
        }

        $redirectUri = $params['redirect_uri'] ?? null;
        if ($redirectUri === null) {
            $redirectUris = $client->getRedirectUris();
            if (count($redirectUris) !== 1) {
                throw new \InvalidArgumentException('redirect_uri is required');
            }
            $redirectUri = $redirectUris[0];
        }

        // Exact string match per RFC 6749
        if (!in_array($redirectUri, $client->getRedirectUris(), true)) {
            throw new \InvalidArgumentException('Invalid redirect_uri');
        }

        // Validate PKCE parameters
        $codeChallenge = $params['code_challenge'] ?? null;
        $codeChallengeMethod = $params['code_challenge_method'] ?? null;
        if ($codeChallenge !== null && $codeChallengeMethod !== null && $codeChallengeMethod !== 'S256') {
            throw new \InvalidArgumentException('Only S256 code_challenge_method is supported');
        }

        // Parse and validate scopes
        $requestedScopes = [];
        if (isset($params['scope']) && $params['scope'] !== '') {
            $requestedScopes = explode(' ', $params['scope']);
        }
        $scopes = $this->scopeRegistry->validate($requestedScopes, $client->getScopes());

        return [
            'client' => $client,
            'scopes' => $scopes,
            'state' => $params['state'] ?? null,
            'redirect_uri' => $redirectUri,
        ];
    }

    /**
     * Generates an authorization code after user consent.
     *
     * @param string|int $userId The authenticated user's ID
     * @param string $clientId The client requesting authorization
     * @param array<string> $scopes Approved scopes
     * @param string $redirectUri The validated redirect URI
     * @param string|null $codeChallenge PKCE code_challenge
     * @param string|null $codeChallengeMethod PKCE method
     * @param string|null $state The state parameter to pass back
     * @return string The redirect URL with code and state
     */
    public function approve(
        string|int $userId,
        string $clientId,
        array $scopes,
        string $redirectUri,
        ?string $codeChallenge = null,
        ?string $codeChallengeMethod = null,
        ?string $state = null,
    ): string {
        $code = bin2hex(random_bytes(32));

        $this->codeStore->store(
            code: $code,
            clientId: $clientId,
            userId: $userId,
            scopes: $scopes,
            expiresAt: new \DateTimeImmutable("+{$this->codeTtl} seconds"),
            redirectUri: $redirectUri,
            codeChallenge: $codeChallenge,
            codeChallengeMethod: $codeChallengeMethod,
        );

        $query = ['code' => $code];
        if ($state !== null) {
            $query['state'] = $state;
        }

        $separator = str_contains($redirectUri, '?') ? '&' : '?';

        return $redirectUri . $separator . http_build_query($query);
    }

    /**
     * Creates a redirect URL for a denied authorization request.
     */
    public function deny(string $redirectUri, ?string $state = null): string
    {
        $query = ['error' => 'access_denied', 'error_description' => 'The user denied the authorization request'];
        if ($state !== null) {
            $query['state'] = $state;
        }

        $separator = str_contains($redirectUri, '?') ? '&' : '?';

        return $redirectUri . $separator . http_build_query($query);
    }
}
