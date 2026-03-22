<?php

declare(strict_types=1);

namespace Lattice\OAuth\Tests;

use Lattice\OAuth\AuthorizationCode;
use Lattice\OAuth\Client;
use Lattice\OAuth\Grant\AuthorizationCodeGrant;
use Lattice\OAuth\Grant\GrantHandlerInterface;
use Lattice\OAuth\Store\InMemoryAuthorizationCodeStore;
use Lattice\OAuth\Store\InMemoryRefreshTokenStore;
use Lattice\OAuth\TokenResponse;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AuthorizationCodeGrantTest extends TestCase
{
    private AuthorizationCodeGrant $grant;
    private InMemoryAuthorizationCodeStore $codeStore;
    private InMemoryRefreshTokenStore $refreshStore;

    protected function setUp(): void
    {
        $this->codeStore = new InMemoryAuthorizationCodeStore();
        $this->refreshStore = new InMemoryRefreshTokenStore();
        $this->grant = new AuthorizationCodeGrant(
            codeStore: $this->codeStore,
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
    public function it_supports_authorization_code_grant_type(): void
    {
        $this->assertTrue($this->grant->supports('authorization_code'));
        $this->assertFalse($this->grant->supports('client_credentials'));
    }

    #[Test]
    public function it_exchanges_valid_code_for_tokens(): void
    {
        $this->codeStore->store(
            code: 'auth-code-123',
            clientId: 'client-1',
            userId: 'user-42',
            scopes: ['read', 'write'],
            expiresAt: new \DateTimeImmutable('+5 minutes'),
        );

        $client = $this->createClient('client-1');

        $response = $this->grant->handle(['code' => 'auth-code-123'], $client);

        $this->assertInstanceOf(TokenResponse::class, $response);
        $this->assertNotEmpty($response->accessToken);
        $this->assertNotEmpty($response->refreshToken);
        $this->assertSame('Bearer', $response->tokenType);
        $this->assertSame(3600, $response->expiresIn);
        $this->assertSame('read write', $response->scope);
    }

    #[Test]
    public function it_throws_for_missing_code(): void
    {
        $client = $this->createClient('client-1');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing authorization code');

        $this->grant->handle([], $client);
    }

    #[Test]
    public function it_throws_for_invalid_code(): void
    {
        $client = $this->createClient('client-1');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid authorization code');

        $this->grant->handle(['code' => 'nonexistent'], $client);
    }

    #[Test]
    public function it_throws_for_expired_code(): void
    {
        $this->codeStore->store(
            code: 'expired-code',
            clientId: 'client-1',
            userId: 'user-42',
            scopes: ['read'],
            expiresAt: new \DateTimeImmutable('-1 minute'),
        );

        $client = $this->createClient('client-1');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Authorization code expired');

        $this->grant->handle(['code' => 'expired-code'], $client);
    }

    #[Test]
    public function it_throws_for_client_id_mismatch(): void
    {
        $this->codeStore->store(
            code: 'code-for-other-client',
            clientId: 'client-other',
            userId: 'user-42',
            scopes: ['read'],
            expiresAt: new \DateTimeImmutable('+5 minutes'),
        );

        $client = $this->createClient('client-1');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Client mismatch');

        $this->grant->handle(['code' => 'code-for-other-client'], $client);
    }

    #[Test]
    public function it_prevents_code_reuse(): void
    {
        $this->codeStore->store(
            code: 'single-use-code',
            clientId: 'client-1',
            userId: 'user-42',
            scopes: ['read'],
            expiresAt: new \DateTimeImmutable('+5 minutes'),
        );

        $client = $this->createClient('client-1');

        // First use succeeds
        $this->grant->handle(['code' => 'single-use-code'], $client);

        // Second use fails
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Authorization code already used');

        $this->grant->handle(['code' => 'single-use-code'], $client);
    }

    private function createClient(string $id): Client
    {
        return new Client(
            id: $id,
            secret: 'secret',
            name: 'Test App',
            redirectUris: ['https://app.test/callback'],
            grantTypes: ['authorization_code'],
            scopes: ['read', 'write'],
        );
    }
}
