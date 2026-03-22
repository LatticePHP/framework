<?php

declare(strict_types=1);

namespace Lattice\Social\Tests;

use Lattice\Social\SocialProviderInterface;
use Lattice\Social\SocialUser;
use Lattice\Social\Testing\FakeSocialProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FakeSocialProviderTest extends TestCase
{
    #[Test]
    public function it_implements_social_provider_interface(): void
    {
        $provider = new FakeSocialProvider('github', $this->createUser());

        $this->assertInstanceOf(SocialProviderInterface::class, $provider);
    }

    #[Test]
    public function it_returns_configured_name(): void
    {
        $provider = new FakeSocialProvider('github', $this->createUser());

        $this->assertSame('github', $provider->getName());
    }

    #[Test]
    public function it_returns_redirect_url_with_state(): void
    {
        $provider = new FakeSocialProvider('github', $this->createUser(), 'https://fake.test/auth');

        $url = $provider->getRedirectUrl('state-abc');

        $this->assertStringContainsString('https://fake.test/auth', $url);
        $this->assertStringContainsString('state=state-abc', $url);
    }

    #[Test]
    public function it_returns_configured_social_user_on_callback(): void
    {
        $user = $this->createUser();
        $provider = new FakeSocialProvider('github', $user);

        $result = $provider->handleCallback(['code' => 'abc']);

        $this->assertSame($user->providerId, $result->providerId);
        $this->assertSame($user->providerName, $result->providerName);
        $this->assertSame($user->email, $result->email);
    }

    private function createUser(): SocialUser
    {
        return new SocialUser(
            providerId: '12345',
            providerName: 'github',
            email: 'john@example.com',
            name: 'John Doe',
            avatar: null,
            accessToken: 'token-abc',
            refreshToken: null,
            raw: ['id' => '12345'],
        );
    }
}
