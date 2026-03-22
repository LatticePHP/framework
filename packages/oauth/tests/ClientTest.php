<?php

declare(strict_types=1);

namespace Lattice\OAuth\Tests;

use Lattice\OAuth\Client;
use Lattice\OAuth\ClientInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ClientTest extends TestCase
{
    #[Test]
    public function it_implements_client_interface(): void
    {
        $client = new Client(
            id: 'client-1',
            secret: 'secret-hash',
            name: 'Test App',
            redirectUris: ['https://app.test/callback'],
            grantTypes: ['authorization_code', 'refresh_token'],
            scopes: ['read', 'write'],
        );

        $this->assertInstanceOf(ClientInterface::class, $client);
    }

    #[Test]
    public function it_exposes_all_properties(): void
    {
        $client = new Client(
            id: 'client-42',
            secret: 'hashed-secret',
            name: 'My App',
            redirectUris: ['https://app.test/cb1', 'https://app.test/cb2'],
            grantTypes: ['client_credentials'],
            scopes: ['admin'],
        );

        $this->assertSame('client-42', $client->getId());
        $this->assertSame('hashed-secret', $client->getSecret());
        $this->assertSame('My App', $client->getName());
        $this->assertSame(['https://app.test/cb1', 'https://app.test/cb2'], $client->getRedirectUris());
        $this->assertSame(['client_credentials'], $client->getGrantTypes());
        $this->assertSame(['admin'], $client->getScopes());
    }
}
