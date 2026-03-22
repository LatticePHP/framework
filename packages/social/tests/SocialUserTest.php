<?php

declare(strict_types=1);

namespace Lattice\Social\Tests;

use Lattice\Social\SocialUser;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SocialUserTest extends TestCase
{
    #[Test]
    public function it_exposes_all_properties(): void
    {
        $raw = ['id' => '12345', 'login' => 'johndoe'];

        $user = new SocialUser(
            providerId: '12345',
            providerName: 'github',
            email: 'john@example.com',
            name: 'John Doe',
            avatar: 'https://example.com/avatar.jpg',
            accessToken: 'gho_abc123',
            refreshToken: 'ghr_refresh456',
            raw: $raw,
        );

        $this->assertSame('12345', $user->providerId);
        $this->assertSame('github', $user->providerName);
        $this->assertSame('john@example.com', $user->email);
        $this->assertSame('John Doe', $user->name);
        $this->assertSame('https://example.com/avatar.jpg', $user->avatar);
        $this->assertSame('gho_abc123', $user->accessToken);
        $this->assertSame('ghr_refresh456', $user->refreshToken);
        $this->assertSame($raw, $user->raw);
    }

    #[Test]
    public function it_allows_nullable_fields(): void
    {
        $user = new SocialUser(
            providerId: '999',
            providerName: 'google',
            email: null,
            name: null,
            avatar: null,
            accessToken: 'token',
            refreshToken: null,
            raw: [],
        );

        $this->assertNull($user->email);
        $this->assertNull($user->name);
        $this->assertNull($user->avatar);
        $this->assertNull($user->refreshToken);
    }
}
