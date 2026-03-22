<?php

declare(strict_types=1);

namespace Lattice\OAuth\Tests;

use Lattice\OAuth\Client;
use Lattice\OAuth\Grant\AuthorizationCodeGrant;
use Lattice\OAuth\Grant\ClientCredentialsGrant;
use Lattice\OAuth\OAuthServer;
use Lattice\OAuth\Store\InMemoryAuthorizationCodeStore;
use Lattice\OAuth\Store\InMemoryClientStore;
use Lattice\OAuth\Store\InMemoryRefreshTokenStore;
use Lattice\OAuth\TokenController;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TokenControllerTest extends TestCase
{
    private TokenController $controller;
    private InMemoryClientStore $clientStore;
    private InMemoryAuthorizationCodeStore $codeStore;

    protected function setUp(): void
    {
        $this->clientStore = new InMemoryClientStore();
        $this->codeStore = new InMemoryAuthorizationCodeStore();
        $refreshStore = new InMemoryRefreshTokenStore();

        $secret = 'test-secret';

        $server = new OAuthServer(
            clientStore: $this->clientStore,
            grantHandlers: [
                new ClientCredentialsGrant(secret: $secret),
                new AuthorizationCodeGrant(
                    codeStore: $this->codeStore,
                    refreshTokenStore: $refreshStore,
                    secret: $secret,
                ),
            ],
        );

        $this->controller = new TokenController($server);

        $this->clientStore->register(new Client(
            id: 'app-1',
            secret: 'app-secret',
            name: 'Test App',
            redirectUris: ['https://app.test/callback'],
            grantTypes: ['client_credentials', 'authorization_code'],
            scopes: ['read', 'write'],
        ));
    }

    #[Test]
    public function it_returns_token_response_array_for_client_credentials(): void
    {
        $result = $this->controller->handleTokenRequest([
            'grant_type' => 'client_credentials',
            'client_id' => 'app-1',
            'client_secret' => 'app-secret',
            'scope' => 'read',
        ]);

        $this->assertArrayHasKey('access_token', $result);
        $this->assertSame('Bearer', $result['token_type']);
        $this->assertSame(3600, $result['expires_in']);
        $this->assertSame('read', $result['scope']);
        $this->assertArrayNotHasKey('refresh_token', $result);
    }

    #[Test]
    public function it_returns_token_response_with_refresh_token_for_auth_code(): void
    {
        $this->codeStore->store(
            code: 'test-code',
            clientId: 'app-1',
            userId: 'user-1',
            scopes: ['read'],
            expiresAt: new \DateTimeImmutable('+5 minutes'),
        );

        $result = $this->controller->handleTokenRequest([
            'grant_type' => 'authorization_code',
            'client_id' => 'app-1',
            'client_secret' => 'app-secret',
            'code' => 'test-code',
        ]);

        $this->assertArrayHasKey('access_token', $result);
        $this->assertArrayHasKey('refresh_token', $result);
        $this->assertSame('Bearer', $result['token_type']);
        $this->assertSame('read', $result['scope']);
    }
}
