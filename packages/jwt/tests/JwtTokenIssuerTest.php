<?php

declare(strict_types=1);

namespace Lattice\Jwt\Tests;

use Lattice\Auth\Principal;
use Lattice\Contracts\Auth\TokenIssuerInterface;
use Lattice\Contracts\Auth\TokenPairInterface;
use Lattice\Jwt\Exception\InvalidTokenException;
use Lattice\Jwt\Exception\RevokedTokenException;
use Lattice\Jwt\JwtConfig;
use Lattice\Jwt\JwtEncoder;
use Lattice\Jwt\JwtTokenIssuer;
use Lattice\Jwt\Store\InMemoryRefreshTokenStore;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class JwtTokenIssuerTest extends TestCase
{
    private JwtTokenIssuer $issuer;
    private JwtEncoder $encoder;
    private InMemoryRefreshTokenStore $store;
    private JwtConfig $config;

    protected function setUp(): void
    {
        $this->encoder = new JwtEncoder();
        $this->store = new InMemoryRefreshTokenStore();
        $this->config = new JwtConfig(
            secret: 'test-secret-key-must-be-long-enough-for-hmac',
            algorithm: 'HS256',
            accessTokenTtl: 3600,
            refreshTokenTtl: 86400,
            issuer: 'lattice-test',
            audience: 'api-test',
        );

        $this->issuer = new JwtTokenIssuer($this->encoder, $this->store, $this->config);
    }

    #[Test]
    public function it_implements_token_issuer_interface(): void
    {
        $this->assertInstanceOf(TokenIssuerInterface::class, $this->issuer);
    }

    #[Test]
    public function it_issues_access_token_pair(): void
    {
        $principal = new Principal(id: 'user-123', scopes: ['read'], roles: ['admin']);

        $pair = $this->issuer->issueAccessToken($principal, ['read']);

        $this->assertInstanceOf(TokenPairInterface::class, $pair);
        $this->assertNotEmpty($pair->getAccessToken());
        $this->assertNotEmpty($pair->getRefreshToken());
        $this->assertSame(3600, $pair->getExpiresIn());
        $this->assertSame('Bearer', $pair->getTokenType());
    }

    #[Test]
    public function access_token_contains_correct_claims(): void
    {
        $principal = new Principal(
            id: 'user-456',
            scopes: ['read', 'write'],
            roles: ['editor'],
        );

        $pair = $this->issuer->issueAccessToken($principal, ['read', 'write']);

        $decoded = $this->encoder->decode($pair->getAccessToken(), $this->config->secret);

        $this->assertSame('user-456', $decoded['sub']);
        $this->assertSame('lattice-test', $decoded['iss']);
        $this->assertSame('api-test', $decoded['aud']);
        $this->assertSame(['read', 'write'], $decoded['scopes']);
        $this->assertSame(['editor'], $decoded['roles']);
        $this->assertArrayHasKey('iat', $decoded);
        $this->assertArrayHasKey('exp', $decoded);
    }

    #[Test]
    public function refresh_token_is_opaque_string(): void
    {
        $principal = new Principal(id: 'user-123');

        $pair = $this->issuer->issueAccessToken($principal);

        // Refresh token should not be a JWT - it should be opaque
        $parts = explode('.', $pair->getRefreshToken());
        $this->assertNotCount(3, $parts, 'Refresh token should not be a JWT');
    }

    #[Test]
    public function it_refreshes_access_token(): void
    {
        $principal = new Principal(id: 'user-123', scopes: ['read'], roles: ['admin']);

        $originalPair = $this->issuer->issueAccessToken($principal, ['read']);
        $newPair = $this->issuer->refreshAccessToken($originalPair->getRefreshToken());

        $this->assertInstanceOf(TokenPairInterface::class, $newPair);
        $this->assertNotEmpty($newPair->getAccessToken());
        $this->assertNotEmpty($newPair->getRefreshToken());
        // New tokens should be different
        $this->assertNotSame($originalPair->getAccessToken(), $newPair->getAccessToken());
    }

    #[Test]
    public function refresh_token_is_revoked_after_use(): void
    {
        $principal = new Principal(id: 'user-123');

        $pair = $this->issuer->issueAccessToken($principal);
        $this->issuer->refreshAccessToken($pair->getRefreshToken());

        // The old refresh token should now be revoked
        $this->expectException(RevokedTokenException::class);
        $this->issuer->refreshAccessToken($pair->getRefreshToken());
    }

    #[Test]
    public function it_revokes_refresh_token(): void
    {
        $principal = new Principal(id: 'user-123');

        $pair = $this->issuer->issueAccessToken($principal);
        $this->issuer->revokeRefreshToken($pair->getRefreshToken());

        $this->expectException(RevokedTokenException::class);
        $this->issuer->refreshAccessToken($pair->getRefreshToken());
    }

    #[Test]
    public function it_throws_for_invalid_refresh_token(): void
    {
        $this->expectException(InvalidTokenException::class);

        $this->issuer->refreshAccessToken('nonexistent-token');
    }

    #[Test]
    public function issued_scopes_default_to_empty(): void
    {
        $principal = new Principal(id: 'user-123');

        $pair = $this->issuer->issueAccessToken($principal);
        $decoded = $this->encoder->decode($pair->getAccessToken(), $this->config->secret);

        $this->assertSame([], $decoded['scopes']);
    }
}
