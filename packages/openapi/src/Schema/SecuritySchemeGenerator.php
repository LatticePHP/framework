<?php

declare(strict_types=1);

namespace Lattice\OpenApi\Schema;

final class SecuritySchemeGenerator
{
    /**
     * Generate a Bearer JWT security scheme.
     *
     * @return array<string, mixed>
     */
    public function bearerJwt(string $name = 'bearerAuth'): array
    {
        return [
            $name => [
                'type' => 'http',
                'scheme' => 'bearer',
                'bearerFormat' => 'JWT',
            ],
        ];
    }

    /**
     * Generate an API key security scheme.
     *
     * @return array<string, mixed>
     */
    public function apiKey(string $name = 'apiKeyAuth', string $in = 'header', string $paramName = 'X-API-Key'): array
    {
        return [
            $name => [
                'type' => 'apiKey',
                'in' => $in,
                'name' => $paramName,
            ],
        ];
    }

    /**
     * Generate an OAuth2 security scheme.
     *
     * @return array<string, mixed>
     */
    public function oauth2(
        string $name = 'oauth2',
        ?string $authorizationUrl = null,
        ?string $tokenUrl = null,
        array $scopes = [],
    ): array {
        $flows = [];

        if ($authorizationUrl !== null && $tokenUrl !== null) {
            $flows['authorizationCode'] = [
                'authorizationUrl' => $authorizationUrl,
                'tokenUrl' => $tokenUrl,
                'scopes' => (object) $scopes,
            ];
        } elseif ($tokenUrl !== null) {
            $flows['clientCredentials'] = [
                'tokenUrl' => $tokenUrl,
                'scopes' => (object) $scopes,
            ];
        }

        return [
            $name => [
                'type' => 'oauth2',
                'flows' => $flows,
            ],
        ];
    }
}
