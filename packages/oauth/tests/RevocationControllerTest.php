<?php

declare(strict_types=1);

namespace Lattice\OAuth\Tests;

use Lattice\OAuth\RevocationController;
use Lattice\OAuth\Store\InMemoryAccessTokenStore;
use Lattice\OAuth\Store\InMemoryRefreshTokenStore;
use Lattice\OAuth\StoredAccessToken;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RevocationControllerTest extends TestCase
{
    private RevocationController $controller;
    private InMemoryAccessTokenStore $accessTokenStore;
    private InMemoryRefreshTokenStore $refreshTokenStore;

    protected function setUp(): void
    {
        $this->accessTokenStore = new InMemoryAccessTokenStore();
        $this->refreshTokenStore = new InMemoryRefreshTokenStore();
        $this->controller = new RevocationController(
            accessTokenStore: $this->accessTokenStore,
            refreshTokenStore: $this->refreshTokenStore,
        );
    }

    #[Test]
    public function it_revokes_access_token(): void
    {
        $now = new \DateTimeImmutable();
        $this->accessTokenStore->store(new StoredAccessToken(
            token: 'access-123',
            clientId: 'client-1',
            userId: 'user-1',
            scopes: ['read'],
            issuedAt: $now,
            expiresAt: $now->modify('+1 hour'),
        ));

        $result = $this->controller->revoke([
            'token' => 'access-123',
            'token_type_hint' => 'access_token',
        ]);

        $this->assertTrue($result);

        $stored = $this->accessTokenStore->find('access-123');
        $this->assertNotNull($stored);
        $this->assertTrue($stored->revoked);
    }

    #[Test]
    public function it_revokes_refresh_token(): void
    {
        $this->refreshTokenStore->store(
            token: 'refresh-abc',
            clientId: 'client-1',
            userId: 'user-1',
            scopes: ['read'],
            expiresAt: new \DateTimeImmutable('+1 day'),
            familyId: 'family-1',
        );

        $result = $this->controller->revoke([
            'token' => 'refresh-abc',
            'token_type_hint' => 'refresh_token',
        ]);

        $this->assertTrue($result);

        $stored = $this->refreshTokenStore->find('refresh-abc');
        $this->assertNotNull($stored);
        $this->assertTrue($stored->revoked);
    }

    #[Test]
    public function it_cascades_revocation_to_access_tokens_in_family(): void
    {
        $now = new \DateTimeImmutable();
        $familyId = 'family-1';

        $this->refreshTokenStore->store(
            token: 'refresh-abc',
            clientId: 'client-1',
            userId: 'user-1',
            scopes: ['read'],
            expiresAt: new \DateTimeImmutable('+1 day'),
            familyId: $familyId,
        );

        $this->accessTokenStore->store(new StoredAccessToken(
            token: 'access-1',
            clientId: 'client-1',
            userId: 'user-1',
            scopes: ['read'],
            issuedAt: $now,
            expiresAt: $now->modify('+1 hour'),
            refreshTokenFamily: $familyId,
        ));

        $this->accessTokenStore->store(new StoredAccessToken(
            token: 'access-2',
            clientId: 'client-1',
            userId: 'user-1',
            scopes: ['read'],
            issuedAt: $now,
            expiresAt: $now->modify('+1 hour'),
            refreshTokenFamily: $familyId,
        ));

        $this->controller->revoke([
            'token' => 'refresh-abc',
            'token_type_hint' => 'refresh_token',
        ]);

        // Both access tokens should be revoked
        $this->assertTrue($this->accessTokenStore->find('access-1')->revoked);
        $this->assertTrue($this->accessTokenStore->find('access-2')->revoked);
    }

    #[Test]
    public function it_always_returns_true_per_rfc(): void
    {
        // Even for nonexistent tokens
        $this->assertTrue($this->controller->revoke(['token' => 'nonexistent']));
        $this->assertTrue($this->controller->revoke([]));
    }

    #[Test]
    public function it_tries_both_stores_with_no_hint(): void
    {
        $now = new \DateTimeImmutable();
        $this->accessTokenStore->store(new StoredAccessToken(
            token: 'some-token',
            clientId: 'client-1',
            userId: 'user-1',
            scopes: ['read'],
            issuedAt: $now,
            expiresAt: $now->modify('+1 hour'),
        ));

        $this->controller->revoke(['token' => 'some-token']);

        $this->assertTrue($this->accessTokenStore->find('some-token')->revoked);
    }
}
