<?php

declare(strict_types=1);

namespace Lattice\OAuth\Tests;

use Lattice\OAuth\OAuthClient;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class OAuthClientTest extends TestCase
{
    #[Test]
    public function it_exposes_all_properties(): void
    {
        $client = new OAuthClient(
            id: 'client-1',
            secretHash: password_hash('secret', PASSWORD_BCRYPT),
            name: 'Test App',
            redirectUris: ['https://app.test/callback'],
            scopes: ['read', 'write'],
            type: 'confidential',
        );

        $this->assertSame('client-1', $client->id);
        $this->assertSame('Test App', $client->name);
        $this->assertSame(['https://app.test/callback'], $client->redirectUris);
        $this->assertSame(['read', 'write'], $client->scopes);
        $this->assertSame('confidential', $client->type);
    }

    #[Test]
    public function it_identifies_confidential_clients(): void
    {
        $client = new OAuthClient(
            id: 'c1',
            secretHash: 'hash',
            name: 'Test',
            type: 'confidential',
        );

        $this->assertTrue($client->isConfidential());
        $this->assertFalse($client->isPublic());
    }

    #[Test]
    public function it_identifies_public_clients(): void
    {
        $client = new OAuthClient(
            id: 'c1',
            secretHash: '',
            name: 'Test',
            type: 'public',
        );

        $this->assertFalse($client->isConfidential());
        $this->assertTrue($client->isPublic());
    }

    #[Test]
    public function it_checks_redirect_uri(): void
    {
        $client = new OAuthClient(
            id: 'c1',
            secretHash: 'hash',
            name: 'Test',
            redirectUris: ['https://app.test/callback', 'https://app.test/auth'],
        );

        $this->assertTrue($client->hasRedirectUri('https://app.test/callback'));
        $this->assertTrue($client->hasRedirectUri('https://app.test/auth'));
        $this->assertFalse($client->hasRedirectUri('https://evil.test/callback'));
    }

    #[Test]
    public function it_checks_scope(): void
    {
        $client = new OAuthClient(
            id: 'c1',
            secretHash: 'hash',
            name: 'Test',
            scopes: ['read', 'write'],
        );

        $this->assertTrue($client->hasScope('read'));
        $this->assertTrue($client->hasScope('write'));
        $this->assertFalse($client->hasScope('admin'));
    }
}
