<?php

declare(strict_types=1);

namespace Lattice\Social\Provider;

use Lattice\Social\SocialUser;

final class GitHubProvider extends AbstractSocialProvider
{
    /** @var array<string> */
    protected array $scopes = ['user:email'];

    public function getName(): string
    {
        return 'github';
    }

    protected function getAuthorizeUrl(): string
    {
        return 'https://github.com/login/oauth/authorize';
    }

    protected function getTokenUrl(): string
    {
        return 'https://github.com/login/oauth/access_token';
    }

    protected function getUserInfoUrl(): string
    {
        return 'https://api.github.com/user';
    }

    protected function mapUserData(array $userData, string $accessToken, ?string $refreshToken): SocialUser
    {
        $email = $userData['email'] ?? null;

        // If email is not public, fetch from /user/emails endpoint
        if ($email === null) {
            $email = $this->fetchPrimaryEmail($accessToken);
        }

        return new SocialUser(
            providerId: (string) ($userData['id'] ?? ''),
            providerName: 'github',
            email: $email,
            name: $userData['name'] ?? null,
            avatar: $userData['avatar_url'] ?? null,
            accessToken: $accessToken,
            refreshToken: $refreshToken,
            raw: $userData,
        );
    }

    private function fetchPrimaryEmail(string $accessToken): ?string
    {
        try {
            $emails = $this->httpGet('https://api.github.com/user/emails', $accessToken);
        } catch (\Throwable) {
            return null;
        }

        foreach ($emails as $emailEntry) {
            if (is_array($emailEntry) && ($emailEntry['primary'] ?? false) && ($emailEntry['verified'] ?? false)) {
                return $emailEntry['email'] ?? null;
            }
        }

        return null;
    }
}
