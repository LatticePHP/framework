<?php

declare(strict_types=1);

namespace Lattice\OAuth\Tests;

use Lattice\OAuth\Client;
use Lattice\OAuth\Grant\AuthorizationCodeGrant;
use Lattice\OAuth\Grant\RefreshTokenGrant;
use Lattice\OAuth\OAuthServer;
use Lattice\OAuth\Store\InMemoryAuthorizationCodeStore;
use Lattice\OAuth\Store\InMemoryClientStore;
use Lattice\OAuth\Store\InMemoryRefreshTokenStore;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RefreshTokenFamilyTest extends TestCase
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

        $secret = 'test-secret';

        $this->server = new OAuthServer(
            clientStore: $this->clientStore,
            grantHandlers: [
                new AuthorizationCodeGrant(
                    codeStore: $this->codeStore,
                    refreshTokenStore: $this->refreshStore,
                    secret: $secret,
                ),
                new RefreshTokenGrant(
                    refreshTokenStore: $this->refreshStore,
                    secret: $secret,
                ),
            ],
        );

        $this->clientStore->register(new Client(
            id: 'app-1',
            secret: 'secret',
            name: 'Test App',
            redirectUris: ['https://app.test/callback'],
            grantTypes: ['authorization_code', 'refresh_token'],
            scopes: ['read', 'write'],
        ));
    }

    #[Test]
    public function it_issues_new_refresh_token_in_same_family_on_rotation(): void
    {
        // Get initial tokens
        $this->codeStore->store(
            code: 'code-1',
            clientId: 'app-1',
            userId: 'user-1',
            scopes: ['read'],
            expiresAt: new \DateTimeImmutable('+5 minutes'),
        );

        $initial = $this->server->handleTokenRequest([
            'grant_type' => 'authorization_code',
            'client_id' => 'app-1',
            'client_secret' => 'secret',
            'code' => 'code-1',
        ]);

        $initialRefresh = $this->refreshStore->find($initial->refreshToken);
        $this->assertNotNull($initialRefresh);
        $familyId = $initialRefresh->familyId;
        $this->assertNotEmpty($familyId);

        // Rotate
        $rotated = $this->server->handleTokenRequest([
            'grant_type' => 'refresh_token',
            'client_id' => 'app-1',
            'client_secret' => 'secret',
            'refresh_token' => $initial->refreshToken,
        ]);

        // New token should be in the same family
        $newRefresh = $this->refreshStore->find($rotated->refreshToken);
        $this->assertNotNull($newRefresh);
        $this->assertSame($familyId, $newRefresh->familyId);

        // Old token should be revoked
        $oldRefresh = $this->refreshStore->find($initial->refreshToken);
        $this->assertNotNull($oldRefresh);
        $this->assertTrue($oldRefresh->revoked);
    }

    #[Test]
    public function it_detects_refresh_token_reuse_and_revokes_family(): void
    {
        // Get initial tokens
        $this->codeStore->store(
            code: 'code-1',
            clientId: 'app-1',
            userId: 'user-1',
            scopes: ['read'],
            expiresAt: new \DateTimeImmutable('+5 minutes'),
        );

        $initial = $this->server->handleTokenRequest([
            'grant_type' => 'authorization_code',
            'client_id' => 'app-1',
            'client_secret' => 'secret',
            'code' => 'code-1',
        ]);

        // First rotation succeeds
        $rotated = $this->server->handleTokenRequest([
            'grant_type' => 'refresh_token',
            'client_id' => 'app-1',
            'client_secret' => 'secret',
            'refresh_token' => $initial->refreshToken,
        ]);

        // Attempt to reuse the old refresh token
        try {
            $this->server->handleTokenRequest([
                'grant_type' => 'refresh_token',
                'client_id' => 'app-1',
                'client_secret' => 'secret',
                'refresh_token' => $initial->refreshToken,
            ]);
            $this->fail('Expected exception for reused refresh token');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('revoked', $e->getMessage());
        }

        // The new refresh token should also be revoked (family revocation)
        $newRefresh = $this->refreshStore->find($rotated->refreshToken);
        $this->assertNotNull($newRefresh);
        $this->assertTrue($newRefresh->revoked);
    }

    #[Test]
    public function it_rejects_expired_refresh_token(): void
    {
        $this->refreshStore->store(
            token: 'expired-token',
            clientId: 'app-1',
            userId: 'user-1',
            scopes: ['read'],
            expiresAt: new \DateTimeImmutable('-1 hour'),
            familyId: 'family-1',
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Refresh token expired');

        $this->server->handleTokenRequest([
            'grant_type' => 'refresh_token',
            'client_id' => 'app-1',
            'client_secret' => 'secret',
            'refresh_token' => 'expired-token',
        ]);
    }
}
