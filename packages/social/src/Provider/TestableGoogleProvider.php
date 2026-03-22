<?php

declare(strict_types=1);

namespace Lattice\Social\Provider;

use Lattice\Social\SocialUser;

/**
 * A testable version of GoogleProvider that allows injection of HTTP responses.
 * Used for unit testing without making real HTTP calls.
 */
final class TestableGoogleProvider extends AbstractSocialProvider
{
    /** @var array<string> */
    protected array $scopes = ['openid', 'profile', 'email'];

    /** @var array<string, mixed>|null */
    private ?array $tokenResponse = null;

    /** @var array<string, mixed>|null */
    private ?array $userResponse = null;

    public function getName(): string
    {
        return 'google';
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

    protected function getAdditionalAuthorizeParams(): array
    {
        return [
            'access_type' => 'offline',
            'prompt' => 'consent',
        ];
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
