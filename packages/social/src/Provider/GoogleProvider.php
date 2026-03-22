<?php

declare(strict_types=1);

namespace Lattice\Social\Provider;

use Lattice\Social\SocialUser;

final class GoogleProvider extends AbstractSocialProvider
{
    /** @var array<string> */
    protected array $scopes = ['openid', 'profile', 'email'];

    public function getName(): string
    {
        return 'google';
    }

    protected function getAuthorizeUrl(): string
    {
        return 'https://accounts.google.com/o/oauth2/v2/auth';
    }

    protected function getTokenUrl(): string
    {
        return 'https://oauth2.googleapis.com/token';
    }

    protected function getUserInfoUrl(): string
    {
        return 'https://www.googleapis.com/oauth2/v3/userinfo';
    }

    /**
     * @return array<string, string>
     */
    protected function getAdditionalAuthorizeParams(): array
    {
        return [
            'access_type' => 'offline',
            'prompt' => 'consent',
        ];
    }

    protected function mapUserData(array $userData, string $accessToken, ?string $refreshToken): SocialUser
    {
        return new SocialUser(
            providerId: (string) ($userData['sub'] ?? ''),
            providerName: 'google',
            email: $userData['email'] ?? null,
            name: $userData['name'] ?? null,
            avatar: $userData['picture'] ?? null,
            accessToken: $accessToken,
            refreshToken: $refreshToken,
            raw: $userData,
        );
    }
}
