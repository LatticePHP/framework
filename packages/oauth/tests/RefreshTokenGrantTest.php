<?php

declare(strict_types=1);

namespace Lattice\OAuth\Tests;

use Lattice\OAuth\Client;
use Lattice\OAuth\Grant\RefreshTokenGrant;
use Lattice\OAuth\Grant\GrantHandlerInterface;
use Lattice\OAuth\Store\InMemoryRefreshTokenStore;
use Lattice\OAuth\TokenResponse;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RefreshTokenGrantTest extends TestCase
{
    private RefreshTokenGrant $grant;
    private InMemoryRefreshTokenStore $refreshStore;

    protected function setUp(): void
    {
        $this->refreshStore = new InMemoryRefreshTokenStore();
        $this->grant = new RefreshTokenGrant(
            refreshTokenStore: $this->refreshStore,
            secret: 'signing-secret',
            accessTokenTtl: 3600,
            refreshTokenTtl: 86400,
        );
    }

    #[Test]
    public function it_implements_grant_handler_interface(): void
    {
        $this->assertInstanceOf(GrantHandlerInterface::class, $this->grant);
    }

    #[Test]
    public function it_supports_refresh_token_grant_type(): void
    {
        $this->assertTrue($this->grant->supports('refresh_token'));
        $this->assertFalse($this->grant->supports('authorization_code'));
    }

    #[Test]
    public function it_issues_new_tokens_for_valid_refresh_token(): void
    {
        $this->refreshStore->store(
            token: 'refresh-abc',
            clientId: 'client-1',
            userId: 'user-42',
            scopes: ['read', 'write'],
            expiresAt: new \DateTimeImmutable('+1 day'),
        );

        $client = $this->createClient('client-1');

        $response = $this->grant->handle(['refresh_token' => 'refresh-abc'], $client);

        $this->assertInstanceOf(TokenResponse::class, $response);
        $this->assertNotEmpty($response->accessToken);
        $this->assertNotEmpty($response->refreshToken);
        $this->assertSame('Bearer', $response->tokenType);
        $this->assertSame(3600, $response->expiresIn);
    }

    #[Test]
    public function it_throws_for_missing_refresh_token(): void
    {
        $client = $this->createClient('client-1');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing refresh token');

        $this->grant->handle([], $client);
    }

    #[Test]
    public function it_throws_for_invalid_refresh_token(): void
    {
        $client = $this->createClient('client-1');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid refresh token');

        $this->grant->handle(['refresh_token' => 'nonexistent'], $client);
    }

    #[Test]
    public function it_throws_for_expired_refresh_token(): void
    {
        $this->refreshStore->store(
            token: 'expired-refresh',
            clientId: 'client-1',
            userId: 'user-42',
            scopes: ['read'],
            expiresAt: new \DateTimeImmutable('-1 hour'),
        );

        $client = $this->createClient('client-1');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Refresh token expired');

        $this->grant->handle(['refresh_token' => 'expired-refresh'], $client);
    }

    #[Test]
    public function it_throws_for_client_id_mismatch(): void
    {
        $this->refreshStore->store(
            token: 'refresh-other',
            clientId: 'client-other',
            userId: 'user-42',
            scopes: ['read'],
            expiresAt: new \DateTimeImmutable('+1 day'),
        );

        $client = $this->createClient('client-1');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Client mismatch');

        $this->grant->handle(['refresh_token' => 'refresh-other'], $client);
    }

    #[Test]
    public function it_revokes_old_refresh_token_after_rotation(): void
    {
        $this->refreshStore->store(
            token: 'old-refresh',
            clientId: 'client-1',
            userId: 'user-42',
            scopes: ['read'],
            expiresAt: new \DateTimeImmutable('+1 day'),
        );

        $client = $this->createClient('client-1');

        $this->grant->handle(['refresh_token' => 'old-refresh'], $client);

        // Old token should be revoked (marked as revoked, not deleted)
        $oldToken = $this->refreshStore->find('old-refresh');
        $this->assertNotNull($oldToken);
        $this->assertTrue($oldToken->revoked);
    }

    private function createClient(string $id): Client
    {
        return new Client(
            id: $id,
            secret: 'secret',
            name: 'Test App',
            redirectUris: ['https://app.test/callback'],
            grantTypes: ['refresh_token'],
            scopes: ['read', 'write'],
        );
    }
}
