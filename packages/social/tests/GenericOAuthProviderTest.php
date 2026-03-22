<?php

declare(strict_types=1);

namespace Lattice\Social\Tests;

use Lattice\Social\Provider\GenericOAuthProvider;
use Lattice\Social\SocialProviderInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class GenericOAuthProviderTest extends TestCase
{
    private GenericOAuthProvider $provider;

    protected function setUp(): void
    {
        $this->provider = new GenericOAuthProvider(
            name: 'custom',
            clientId: 'client-id',
            clientSecret: 'client-secret',
            authorizeUrl: 'https://provider.test/authorize',
            tokenUrl: 'https://provider.test/token',
            userInfoUrl: 'https://provider.test/userinfo',
        );
    }

    #[Test]
    public function it_implements_social_provider_interface(): void
    {
        $this->assertInstanceOf(SocialProviderInterface::class, $this->provider);
    }

    #[Test]
    public function it_returns_configured_name(): void
    {
        $this->assertSame('custom', $this->provider->getName());
    }

    #[Test]
    public function it_builds_redirect_url_with_required_params(): void
    {
        $url = $this->provider->getRedirectUrl('state-123');

        $parsed = parse_url($url);
        parse_str($parsed['query'] ?? '', $params);

        $this->assertSame('https', $parsed['scheme']);
        $this->assertSame('provider.test', $parsed['host']);
        $this->assertSame('/authorize', $parsed['path']);
        $this->assertSame('client-id', $params['client_id']);
        $this->assertSame('code', $params['response_type']);
        $this->assertSame('state-123', $params['state']);
    }

    #[Test]
    public function it_includes_redirect_uri_when_configured(): void
    {
        $provider = new GenericOAuthProvider(
            name: 'custom',
            clientId: 'client-id',
            clientSecret: 'client-secret',
            authorizeUrl: 'https://provider.test/authorize',
            tokenUrl: 'https://provider.test/token',
            userInfoUrl: 'https://provider.test/userinfo',
            redirectUri: 'https://myapp.test/callback',
        );

        $url = $provider->getRedirectUrl('state-123');

        $parsed = parse_url($url);
        parse_str($parsed['query'] ?? '', $params);

        $this->assertSame('https://myapp.test/callback', $params['redirect_uri']);
    }

    #[Test]
    public function it_includes_scopes_when_configured(): void
    {
        $provider = new GenericOAuthProvider(
            name: 'custom',
            clientId: 'client-id',
            clientSecret: 'client-secret',
            authorizeUrl: 'https://provider.test/authorize',
            tokenUrl: 'https://provider.test/token',
            userInfoUrl: 'https://provider.test/userinfo',
            scopes: ['openid', 'profile', 'email'],
        );

        $url = $provider->getRedirectUrl('state-123');

        $parsed = parse_url($url);
        parse_str($parsed['query'] ?? '', $params);

        $this->assertSame('openid profile email', $params['scope']);
    }

    #[Test]
    public function it_supports_custom_field_mapping(): void
    {
        $provider = new GenericOAuthProvider(
            name: 'custom',
            clientId: 'client-id',
            clientSecret: 'client-secret',
            authorizeUrl: 'https://provider.test/authorize',
            tokenUrl: 'https://provider.test/token',
            userInfoUrl: 'https://provider.test/userinfo',
            fieldMapping: [
                'id' => 'sub',
                'email' => 'email_address',
                'name' => 'display_name',
                'avatar' => 'picture_url',
            ],
        );

        $this->assertSame('custom', $provider->getName());
    }
}
