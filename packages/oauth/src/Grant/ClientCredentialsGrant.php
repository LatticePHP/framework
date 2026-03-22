<?php

declare(strict_types=1);

namespace Lattice\OAuth\Grant;

use Lattice\OAuth\ClientInterface;
use Lattice\OAuth\TokenResponse;

final class ClientCredentialsGrant implements GrantHandlerInterface
{
    public function __construct(
        private readonly string $secret,
        private readonly int $accessTokenTtl = 3600,
    ) {}

    public function supports(string $grantType): bool
    {
        return $grantType === 'client_credentials';
    }

    public function handle(array $params, ClientInterface $client): TokenResponse
    {
        $requestedScopes = $this->parseScopes($params['scope'] ?? null, $client);

        $accessToken = $this->generateAccessToken($client->getId(), $requestedScopes);

        return new TokenResponse(
            accessToken: $accessToken,
            tokenType: 'Bearer',
            expiresIn: $this->accessTokenTtl,
            refreshToken: null,
            scope: implode(' ', $requestedScopes),
        );
    }

    /**
     * @return array<string>
     */
    private function parseScopes(?string $scopeString, ClientInterface $client): array
    {
        $clientScopes = $client->getScopes();

        if ($scopeString === null || $scopeString === '') {
            return $clientScopes;
        }

        $requested = explode(' ', $scopeString);

        foreach ($requested as $scope) {
            if (!in_array($scope, $clientScopes, true)) {
                throw new \InvalidArgumentException("Invalid scope: {$scope}");
            }
        }

        return $requested;
    }

    /**
     * @param array<string> $scopes
     */
    private function generateAccessToken(string $clientId, array $scopes): string
    {
        $payload = [
            'sub' => $clientId,
            'type' => 'client_credentials',
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
