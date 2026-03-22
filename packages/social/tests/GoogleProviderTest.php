<?php

declare(strict_types=1);

namespace Lattice\Social\Tests;

use Lattice\Social\Provider\TestableGoogleProvider;
use Lattice\Social\SocialProviderInterface;
use Lattice\Social\SocialUser;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class GoogleProviderTest extends TestCase
{
    private TestableGoogleProvider $provider;

    protected function setUp(): void
    {
        $this->provider = new TestableGoogleProvider(
            clientId: 'google-client-id',
            clientSecret: 'google-client-secret',
            redirectUri: 'https://myapp.test/auth/google/callback',
        );
    }

    #[Test]
    public function it_implements_social_provider_interface(): void
    {
        $this->assertInstanceOf(SocialProviderInterface::class, $this->provider);
    }

    #[Test]
    public function it_returns_google_as_name(): void
    {
        $this->assertSame('google', $this->provider->getName());
    }

    #[Test]
    public function it_builds_correct_redirect_url(): void
    {
        $url = $this->provider->getRedirectUrl('state-xyz');

        $parsed = parse_url($url);
        parse_str($parsed['query'] ?? '', $params);

        $this->assertSame('https', $parsed['scheme']);
        $this->assertSame('accounts.google.com', $parsed['host']);
        $this->assertSame('/o/oauth2/v2/auth', $parsed['path']);
        $this->assertSame('google-client-id', $params['client_id']);
        $this->assertSame('code', $params['response_type']);
        $this->assertSame('state-xyz', $params['state']);
        $this->assertSame('https://myapp.test/auth/google/callback', $params['redirect_uri']);
        $this->assertSame('openid profile email', $params['scope']);
    }

    #[Test]
    public function it_includes_google_specific_params(): void
    {
        $url = $this->provider->getRedirectUrl('state');

        parse_str(parse_url($url, PHP_URL_QUERY) ?? '', $params);

        $this->assertSame('offline', $params['access_type']);
        $this->assertSame('consent', $params['prompt']);
    }

    #[Test]
    public function it_exchanges_code_and_returns_social_user(): void
    {
        $this->provider->setTokenResponse([
            'access_token' => 'ya29.test-token',
            'token_type' => 'Bearer',
            'expires_in' => 3600,
            'refresh_token' => 'refresh-token-123',
            'scope' => 'openid profile email',
        ]);

        $this->provider->setUserResponse([
            'sub' => '108234567890123456789',
            'name' => 'Jane Smith',
            'given_name' => 'Jane',
            'family_name' => 'Smith',
            'picture' => 'https://lh3.googleusercontent.com/a/photo.jpg',
            'email' => 'jane@gmail.com',
            'email_verified' => true,
        ]);

        $user = $this->provider->handleCallback(['code' => 'google-auth-code']);

        $this->assertInstanceOf(SocialUser::class, $user);
        $this->assertSame('108234567890123456789', $user->providerId);
        $this->assertSame('google', $user->providerName);
        $this->assertSame('jane@gmail.com', $user->email);
        $this->assertSame('Jane Smith', $user->name);
        $this->assertSame('https://lh3.googleusercontent.com/a/photo.jpg', $user->avatar);
        $this->assertSame('ya29.test-token', $user->accessToken);
        $this->assertSame('refresh-token-123', $user->refreshToken);
    }

    #[Test]
    public function it_handles_minimal_google_profile(): void
    {
        $this->provider->setTokenResponse([
            'access_token' => 'ya29.test-token',
        ]);

        $this->provider->setUserResponse([
            'sub' => '123456',
        ]);

        $user = $this->provider->handleCallback(['code' => 'code']);

        $this->assertSame('123456', $user->providerId);
        $this->assertSame('google', $user->providerName);
        $this->assertNull($user->email);
        $this->assertNull($user->name);
        $this->assertNull($user->avatar);
    }

    #[Test]
    public function it_throws_for_missing_code(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing authorization code');

        $this->provider->handleCallback([]);
    }

    #[Test]
    public function it_throws_when_provider_returns_error(): void
    {
        $this->expectException(\RuntimeException::class);

        $this->provider->handleCallback([
            'error' => 'access_denied',
        ]);
    }

    #[Test]
    public function it_throws_for_missing_access_token_in_response(): void
    {
        $this->provider->setTokenResponse([
            'error' => 'invalid_grant',
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No access_token');

        $this->provider->handleCallback(['code' => 'code']);
    }
}
