<?php

declare(strict_types=1);

namespace Lattice\Social\Tests;

use Lattice\Social\Provider\AbstractSocialProvider;
use Lattice\Social\Provider\TestableGitHubProvider;
use Lattice\Social\SocialProviderInterface;
use Lattice\Social\SocialUser;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class GitHubProviderTest extends TestCase
{
    private TestableGitHubProvider $provider;

    protected function setUp(): void
    {
        $this->provider = new TestableGitHubProvider(
            clientId: 'github-client-id',
            clientSecret: 'github-client-secret',
            redirectUri: 'https://myapp.test/auth/github/callback',
        );
    }

    #[Test]
    public function it_implements_social_provider_interface(): void
    {
        $this->assertInstanceOf(SocialProviderInterface::class, $this->provider);
    }

    #[Test]
    public function it_returns_github_as_name(): void
    {
        $this->assertSame('github', $this->provider->getName());
    }

    #[Test]
    public function it_builds_correct_redirect_url(): void
    {
        $url = $this->provider->getRedirectUrl('state-abc');

        $parsed = parse_url($url);
        parse_str($parsed['query'] ?? '', $params);

        $this->assertSame('https', $parsed['scheme']);
        $this->assertSame('github.com', $parsed['host']);
        $this->assertSame('/login/oauth/authorize', $parsed['path']);
        $this->assertSame('github-client-id', $params['client_id']);
        $this->assertSame('code', $params['response_type']);
        $this->assertSame('state-abc', $params['state']);
        $this->assertSame('https://myapp.test/auth/github/callback', $params['redirect_uri']);
        $this->assertSame('user:email', $params['scope']);
    }

    #[Test]
    public function it_exchanges_code_and_returns_social_user(): void
    {
        $this->provider->setTokenResponse([
            'access_token' => 'gho_test_token',
            'token_type' => 'bearer',
            'scope' => 'user:email',
        ]);

        $this->provider->setUserResponse([
            'id' => 12345,
            'login' => 'johndoe',
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'avatar_url' => 'https://avatars.githubusercontent.com/u/12345',
        ]);

        $user = $this->provider->handleCallback(['code' => 'auth-code-abc']);

        $this->assertInstanceOf(SocialUser::class, $user);
        $this->assertSame('12345', $user->providerId);
        $this->assertSame('github', $user->providerName);
        $this->assertSame('john@example.com', $user->email);
        $this->assertSame('John Doe', $user->name);
        $this->assertSame('https://avatars.githubusercontent.com/u/12345', $user->avatar);
        $this->assertSame('gho_test_token', $user->accessToken);
        $this->assertNull($user->refreshToken);
    }

    #[Test]
    public function it_fetches_email_from_emails_endpoint_when_not_public(): void
    {
        $this->provider->setTokenResponse([
            'access_token' => 'gho_test_token',
        ]);

        $this->provider->setUserResponse([
            'id' => 12345,
            'login' => 'johndoe',
            'name' => 'John Doe',
            'email' => null,
            'avatar_url' => 'https://avatars.githubusercontent.com/u/12345',
        ]);

        $this->provider->setEmailsResponse([
            ['email' => 'noreply@users.github.com', 'primary' => false, 'verified' => true],
            ['email' => 'john@example.com', 'primary' => true, 'verified' => true],
        ]);

        $user = $this->provider->handleCallback(['code' => 'auth-code']);

        $this->assertSame('john@example.com', $user->email);
    }

    #[Test]
    public function it_handles_missing_email(): void
    {
        $this->provider->setTokenResponse([
            'access_token' => 'gho_test_token',
        ]);

        $this->provider->setUserResponse([
            'id' => 12345,
            'login' => 'johndoe',
            'name' => 'John Doe',
            'email' => null,
            'avatar_url' => null,
        ]);

        // No emails response set, so email stays null
        $user = $this->provider->handleCallback(['code' => 'auth-code']);

        $this->assertNull($user->email);
    }

    #[Test]
    public function it_throws_for_missing_code_in_callback(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing authorization code');

        $this->provider->handleCallback([]);
    }

    #[Test]
    public function it_throws_when_provider_returns_error(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Provider returned error');

        $this->provider->handleCallback([
            'error' => 'access_denied',
            'error_description' => 'The user denied access',
        ]);
    }

    #[Test]
    public function it_generates_state_parameter(): void
    {
        $state = AbstractSocialProvider::generateState();

        $this->assertSame(64, strlen($state));
        $this->assertMatchesRegularExpression('/^[0-9a-f]+$/', $state);
    }

    #[Test]
    public function it_validates_state_parameter(): void
    {
        $state = AbstractSocialProvider::generateState();

        $this->assertTrue(AbstractSocialProvider::validateState($state, $state));
        $this->assertFalse(AbstractSocialProvider::validateState($state, 'wrong-state'));
    }

    #[Test]
    public function it_supports_custom_scopes(): void
    {
        $this->provider->setScopes(['read:user', 'repo']);

        $url = $this->provider->getRedirectUrl('state');
        parse_str(parse_url($url, PHP_URL_QUERY) ?? '', $params);

        $this->assertSame('read:user repo', $params['scope']);
    }
}
