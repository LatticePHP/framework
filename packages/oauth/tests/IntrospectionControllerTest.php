<?php

declare(strict_types=1);

namespace Lattice\OAuth\Tests;

use Lattice\OAuth\IntrospectionController;
use Lattice\OAuth\Store\InMemoryAccessTokenStore;
use Lattice\OAuth\Store\InMemoryRefreshTokenStore;
use Lattice\OAuth\StoredAccessToken;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class IntrospectionControllerTest extends TestCase
{
    private IntrospectionController $controller;
    private InMemoryAccessTokenStore $accessTokenStore;
    private InMemoryRefreshTokenStore $refreshTokenStore;

    protected function setUp(): void
    {
        $this->accessTokenStore = new InMemoryAccessTokenStore();
        $this->refreshTokenStore = new InMemoryRefreshTokenStore();
        $this->controller = new IntrospectionController(
            accessTokenStore: $this->accessTokenStore,
            refreshTokenStore: $this->refreshTokenStore,
        );
    }

    #[Test]
    public function it_returns_active_true_for_valid_access_token(): void
    {
        $now = new \DateTimeImmutable();
        $this->accessTokenStore->store(new StoredAccessToken(
            token: 'valid-token',
            clientId: 'client-1',
            userId: 'user-1',
            scopes: ['read', 'write'],
            issuedAt: $now,
            expiresAt: $now->modify('+1 hour'),
        ));

        $result = $this->controller->introspect(['token' => 'valid-token']);

        $this->assertTrue($result['active']);
        $this->assertSame('read write', $result['scope']);
        $this->assertSame('client-1', $result['client_id']);
        $this->assertSame('user-1', $result['username']);
        $this->assertSame('Bearer', $result['token_type']);
        $this->assertArrayHasKey('exp', $result);
        $this->assertArrayHasKey('iat', $result);
    }

    #[Test]
    public function it_returns_active_false_for_expired_access_token(): void
    {
        $now = new \DateTimeImmutable();
        $this->accessTokenStore->store(new StoredAccessToken(
            token: 'expired-token',
            clientId: 'client-1',
            userId: 'user-1',
            scopes: ['read'],
            issuedAt: $now->modify('-2 hours'),
            expiresAt: $now->modify('-1 hour'),
        ));

        $result = $this->controller->introspect(['token' => 'expired-token']);

        $this->assertFalse($result['active']);
    }

    #[Test]
    public function it_returns_active_false_for_revoked_access_token(): void
    {
        $now = new \DateTimeImmutable();
        $this->accessTokenStore->store(new StoredAccessToken(
            token: 'revoked-token',
            clientId: 'client-1',
            userId: 'user-1',
            scopes: ['read'],
            issuedAt: $now,
            expiresAt: $now->modify('+1 hour'),
            revoked: true,
        ));

        $result = $this->controller->introspect(['token' => 'revoked-token']);

        $this->assertFalse($result['active']);
    }

    #[Test]
    public function it_returns_active_false_for_unknown_token(): void
    {
        $result = $this->controller->introspect(['token' => 'nonexistent']);

        $this->assertFalse($result['active']);
    }

    #[Test]
    public function it_introspects_refresh_token_with_hint(): void
    {
        $this->refreshTokenStore->store(
            token: 'refresh-abc',
            clientId: 'client-1',
            userId: 'user-1',
            scopes: ['read'],
            expiresAt: new \DateTimeImmutable('+1 day'),
        );

        $result = $this->controller->introspect([
            'token' => 'refresh-abc',
            'token_type_hint' => 'refresh_token',
        ]);

        $this->assertTrue($result['active']);
        $this->assertSame('client-1', $result['client_id']);
        $this->assertSame('refresh_token', $result['token_type']);
    }

    #[Test]
    public function it_returns_active_false_for_revoked_refresh_token(): void
    {
        $this->refreshTokenStore->store(
            token: 'revoked-refresh',
            clientId: 'client-1',
            userId: 'user-1',
            scopes: ['read'],
            expiresAt: new \DateTimeImmutable('+1 day'),
        );
        $this->refreshTokenStore->revoke('revoked-refresh');

        $result = $this->controller->introspect([
            'token' => 'revoked-refresh',
            'token_type_hint' => 'refresh_token',
        ]);

        $this->assertFalse($result['active']);
    }

    #[Test]
    public function it_throws_for_missing_token_parameter(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing token parameter');

        $this->controller->introspect([]);
    }

    #[Test]
    public function it_falls_back_to_refresh_token_when_no_hint(): void
    {
        $this->refreshTokenStore->store(
            token: 'some-token',
            clientId: 'client-1',
            userId: 'user-1',
            scopes: ['read'],
            expiresAt: new \DateTimeImmutable('+1 day'),
        );

        $result = $this->controller->introspect(['token' => 'some-token']);

        $this->assertTrue($result['active']);
        $this->assertSame('refresh_token', $result['token_type']);
    }
}
