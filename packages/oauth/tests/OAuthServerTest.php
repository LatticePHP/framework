<?php

declare(strict_types=1);

namespace Lattice\OAuth\Tests;

use Lattice\OAuth\Client;
use Lattice\OAuth\Grant\AuthorizationCodeGrant;
use Lattice\OAuth\Grant\ClientCredentialsGrant;
use Lattice\OAuth\Grant\RefreshTokenGrant;
use Lattice\OAuth\OAuthServer;
use Lattice\OAuth\Store\InMemoryAuthorizationCodeStore;
use Lattice\OAuth\Store\InMemoryClientStore;
use Lattice\OAuth\Store\InMemoryRefreshTokenStore;
use Lattice\OAuth\TokenResponse;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class OAuthServerTest extends TestCase
{
    private OAuthServer $server;
    private InMemoryClientStore $clientStore;
    private InMemoryAuthorizationCodeStore $codeStore;
    private InMemoryRefreshTokenStore $refreshStore;

    protected function setUp(): void
    {
        $this->clientStore = new InMemoryClientStore();
        $this->codeStore = new InMemoryAuthorizationCodeStore();
        $this->refreshStore = new InMemoryRefreshTokenStore();

        $secret = 'test-signing-secret';

        $this->server = new OAuthServer(
            clientStore: $this->clientStore,
            grantHandlers: [
                new ClientCredentialsGrant(secret: $secret, accessTokenTtl: 3600),
                new AuthorizationCodeGrant(
                    codeStore: $this->codeStore,
                    refreshTokenStore: $this->refreshStore,
                    secret: $secret,
                    accessTokenTtl: 3600,
                    refreshTokenTtl: 86400,
                ),
                new RefreshTokenGrant(
                    refreshTokenStore: $this->refreshStore,
                    secret: $secret,
                    accessTokenTtl: 3600,
                    refreshTokenTtl: 86400,
                ),
            ],
        );

        $this->clientStore->register(new Client(
            id: 'app-1',
            secret: 'app-secret',
            name: 'Test Application',
            redirectUris: ['https://app.test/callback'],
            grantTypes: ['client_credentials', 'authorization_code', 'refresh_token'],
            scopes: ['read', 'write', 'admin'],
        ));
    }

    #[Test]
    public function it_handles_client_credentials_flow(): void
    {
        $response = $this->server->handleTokenRequest([
            'grant_type' => 'client_credentials',
            'client_id' => 'app-1',
            'client_secret' => 'app-secret',
            'scope' => 'read write',
        ]);

        $this->assertInstanceOf(TokenResponse::class, $response);
        $this->assertNotEmpty($response->accessToken);
        $this->assertSame('Bearer', $response->tokenType);
        $this->assertSame(3600, $response->expiresIn);
        $this->assertNull($response->refreshToken);
        $this->assertSame('read write', $response->scope);
    }

    #[Test]
    public function it_handles_authorization_code_flow(): void
    {
        $this->codeStore->store(
            code: 'auth-code-xyz',
            clientId: 'app-1',
            userId: 'user-1',
            scopes: ['read'],
            expiresAt: new \DateTimeImmutable('+5 minutes'),
        );

        $response = $this->server->handleTokenRequest([
            'grant_type' => 'authorization_code',
            'client_id' => 'app-1',
            'client_secret' => 'app-secret',
            'code' => 'auth-code-xyz',
        ]);

        $this->assertInstanceOf(TokenResponse::class, $response);
        $this->assertNotEmpty($response->accessToken);
        $this->assertNotEmpty($response->refreshToken);
        $this->assertSame('read', $response->scope);
    }

    #[Test]
    public function it_handles_refresh_token_flow(): void
    {
        // First, get tokens via auth code
        $this->codeStore->store(
            code: 'code-for-refresh',
            clientId: 'app-1',
            userId: 'user-1',
            scopes: ['read', 'write'],
            expiresAt: new \DateTimeImmutable('+5 minutes'),
        );

        $initial = $this->server->handleTokenRequest([
            'grant_type' => 'authorization_code',
            'client_id' => 'app-1',
            'client_secret' => 'app-secret',
            'code' => 'code-for-refresh',
        ]);

        // Now refresh
        $response = $this->server->handleTokenRequest([
            'grant_type' => 'refresh_token',
            'client_id' => 'app-1',
            'client_secret' => 'app-secret',
            'refresh_token' => $initial->refreshToken,
        ]);

        $this->assertInstanceOf(TokenResponse::class, $response);
        $this->assertNotEmpty($response->accessToken);
        $this->assertNotEmpty($response->refreshToken);
        $this->assertNotSame($initial->accessToken, $response->accessToken);
    }

    #[Test]
    public function it_throws_for_missing_grant_type(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing grant_type');

        $this->server->handleTokenRequest([
            'client_id' => 'app-1',
            'client_secret' => 'app-secret',
        ]);
    }

    #[Test]
    public function it_throws_for_missing_client_credentials(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing client credentials');

        $this->server->handleTokenRequest([
            'grant_type' => 'client_credentials',
        ]);
    }

    #[Test]
    public function it_throws_for_invalid_client_credentials(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid client credentials');

        $this->server->handleTokenRequest([
            'grant_type' => 'client_credentials',
            'client_id' => 'app-1',
            'client_secret' => 'wrong-secret',
        ]);
    }

    #[Test]
    public function it_throws_for_unknown_client(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid client credentials');

        $this->server->handleTokenRequest([
            'grant_type' => 'client_credentials',
            'client_id' => 'unknown',
            'client_secret' => 'secret',
        ]);
    }

    #[Test]
    public function it_throws_for_unsupported_grant_type(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported grant type');

        $this->server->handleTokenRequest([
            'grant_type' => 'password',
            'client_id' => 'app-1',
            'client_secret' => 'app-secret',
        ]);
    }

    #[Test]
    public function it_throws_when_client_does_not_support_grant_type(): void
    {
        $this->clientStore->register(new Client(
            id: 'limited-app',
            secret: 'secret',
            name: 'Limited App',
            redirectUris: [],
            grantTypes: ['client_credentials'],
            scopes: ['read'],
        ));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Grant type not allowed');

        $this->server->handleTokenRequest([
            'grant_type' => 'authorization_code',
            'client_id' => 'limited-app',
            'client_secret' => 'secret',
            'code' => 'some-code',
        ]);
    }
}
