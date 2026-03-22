<?php

declare(strict_types=1);

namespace Lattice\OAuth\Tests;

use Lattice\OAuth\Client;
use Lattice\OAuth\Grant\ClientCredentialsGrant;
use Lattice\OAuth\Grant\GrantHandlerInterface;
use Lattice\OAuth\TokenResponse;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ClientCredentialsGrantTest extends TestCase
{
    private ClientCredentialsGrant $grant;

    protected function setUp(): void
    {
        $this->grant = new ClientCredentialsGrant(
            secret: 'signing-secret-for-tokens',
            accessTokenTtl: 3600,
        );
    }

    #[Test]
    public function it_implements_grant_handler_interface(): void
    {
        $this->assertInstanceOf(GrantHandlerInterface::class, $this->grant);
    }

    #[Test]
    public function it_supports_client_credentials_grant_type(): void
    {
        $this->assertTrue($this->grant->supports('client_credentials'));
        $this->assertFalse($this->grant->supports('authorization_code'));
    }

    #[Test]
    public function it_issues_access_token_for_valid_client(): void
    {
        $client = new Client(
            id: 'client-1',
            secret: 'secret',
            name: 'Test',
            redirectUris: [],
            grantTypes: ['client_credentials'],
            scopes: ['read', 'write'],
        );

        $response = $this->grant->handle(['scope' => 'read'], $client);

        $this->assertInstanceOf(TokenResponse::class, $response);
        $this->assertNotEmpty($response->accessToken);
        $this->assertSame('Bearer', $response->tokenType);
        $this->assertSame(3600, $response->expiresIn);
        $this->assertNull($response->refreshToken);
        $this->assertSame('read', $response->scope);
    }

    #[Test]
    public function it_uses_all_client_scopes_when_none_requested(): void
    {
        $client = new Client(
            id: 'client-1',
            secret: 'secret',
            name: 'Test',
            redirectUris: [],
            grantTypes: ['client_credentials'],
            scopes: ['read', 'write'],
        );

        $response = $this->grant->handle([], $client);

        $this->assertSame('read write', $response->scope);
    }

    #[Test]
    public function it_throws_when_requested_scope_exceeds_client_scopes(): void
    {
        $client = new Client(
            id: 'client-1',
            secret: 'secret',
            name: 'Test',
            redirectUris: [],
            grantTypes: ['client_credentials'],
            scopes: ['read'],
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid scope');

        $this->grant->handle(['scope' => 'read admin'], $client);
    }
}
