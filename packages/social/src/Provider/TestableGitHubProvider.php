<?php

declare(strict_types=1);

namespace Lattice\Social\Provider;

use Lattice\Social\SocialUser;

/**
 * A testable version of GitHubProvider that allows injection of HTTP responses.
 * Used for unit testing without making real HTTP calls.
 */
final class TestableGitHubProvider extends AbstractSocialProvider
{
    /** @var array<string> */
    protected array $scopes = ['user:email'];

    /** @var array<string, mixed>|null */
    private ?array $tokenResponse = null;

    /** @var array<string, mixed>|null */
    private ?array $userResponse = null;

    /** @var array<array<string, mixed>>|null */
    private ?array $emailsResponse = null;

    public function getName(): string
    {
        return 'github';
    }

    /**
     * @param array<string, mixed> $response
     */
    public function setTokenResponse(array $response): void
    {
        $this->tokenResponse = $response;
    }

    /**
     * @param array<string, mixed> $response
     */
    public function setUserResponse(array $response): void
    {
        $this->userResponse = $response;
    }

    /**
     * @param array<array<string, mixed>> $response
     */
    public function setEmailsResponse(array $response): void
    {
        $this->emailsResponse = $response;
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

    protected function exchangeCode(string $code): array
    {
        if ($this->tokenResponse !== null) {
            return $this->tokenResponse;
        }

        return parent::exchangeCode($code);
    }

    protected function fetchUserInfo(string $accessToken): array
    {
        if ($this->userResponse !== null) {
            return $this->userResponse;
        }

        return parent::fetchUserInfo($accessToken);
    }

    protected function mapUserData(array $userData, string $accessToken, ?string $refreshToken): SocialUser
    {
        $email = $userData['email'] ?? null;

        // If email is not public, try the injected emails response
        if ($email === null && $this->emailsResponse !== null) {
            foreach ($this->emailsResponse as $emailEntry) {
                if (($emailEntry['primary'] ?? false) && ($emailEntry['verified'] ?? false)) {
                    $email = $emailEntry['email'] ?? null;
                    break;
                }
            }
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
}
