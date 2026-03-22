<?php

declare(strict_types=1);

namespace Lattice\Social\Tests;

use Lattice\Social\SocialAuthManager;
use Lattice\Social\SocialUser;
use Lattice\Social\Testing\FakeSocialProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SocialAuthManagerTest extends TestCase
{
    private SocialAuthManager $manager;
    private FakeSocialProvider $githubProvider;

    protected function setUp(): void
    {
        $this->manager = new SocialAuthManager();

        $user = new SocialUser(
            providerId: 'gh-123',
            providerName: 'github',
            email: 'dev@example.com',
            name: 'Dev User',
            avatar: null,
            accessToken: 'gho_token',
            refreshToken: null,
            raw: [],
        );

        $this->githubProvider = new FakeSocialProvider('github', $user, 'https://github.com/login/oauth/authorize');
        $this->manager->registerProvider('github', $this->githubProvider);
    }

    #[Test]
    public function it_registers_and_retrieves_provider(): void
    {
        $provider = $this->manager->getProvider('github');

        $this->assertSame($this->githubProvider, $provider);
    }

    #[Test]
    public function it_throws_for_unknown_provider(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown social provider: twitter');

        $this->manager->getProvider('twitter');
    }

    #[Test]
    public function it_returns_redirect_url(): void
    {
        $url = $this->manager->getRedirectUrl('github', 'state-xyz');

        $this->assertStringContainsString('https://github.com/login/oauth/authorize', $url);
        $this->assertStringContainsString('state=state-xyz', $url);
    }

    #[Test]
    public function it_throws_for_redirect_with_unknown_provider(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->manager->getRedirectUrl('twitter', 'state');
    }

    #[Test]
    public function it_handles_callback(): void
    {
        $user = $this->manager->handleCallback('github', ['code' => 'auth-code']);

        $this->assertInstanceOf(SocialUser::class, $user);
        $this->assertSame('gh-123', $user->providerId);
        $this->assertSame('github', $user->providerName);
        $this->assertSame('dev@example.com', $user->email);
    }

    #[Test]
    public function it_throws_for_callback_with_unknown_provider(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->manager->handleCallback('twitter', ['code' => 'abc']);
    }

    #[Test]
    public function it_supports_multiple_providers(): void
    {
        $googleUser = new SocialUser(
            providerId: 'g-456',
            providerName: 'google',
            email: 'user@gmail.com',
            name: 'Google User',
            avatar: null,
            accessToken: 'google-token',
            refreshToken: null,
            raw: [],
        );

        $this->manager->registerProvider('google', new FakeSocialProvider('google', $googleUser));

        $ghUser = $this->manager->handleCallback('github', ['code' => 'abc']);
        $gUser = $this->manager->handleCallback('google', ['code' => 'xyz']);

        $this->assertSame('gh-123', $ghUser->providerId);
        $this->assertSame('g-456', $gUser->providerId);
    }
}
