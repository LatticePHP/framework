<?php

declare(strict_types=1);

namespace Lattice\Social\Provider;

use Lattice\Social\SocialProviderInterface;
use Lattice\Social\SocialUser;

abstract class AbstractSocialProvider implements SocialProviderInterface
{
    /** @var array<string> */
    protected array $scopes = [];

    public function __construct(
        protected readonly string $clientId,
        protected readonly string $clientSecret,
        protected readonly ?string $redirectUri = null,
    ) {}

    abstract protected function getAuthorizeUrl(): string;

    abstract protected function getTokenUrl(): string;

    abstract protected function getUserInfoUrl(): string;

    abstract protected function mapUserData(array $userData, string $accessToken, ?string $refreshToken): SocialUser;

    public function getRedirectUrl(string $state): string
    {
        $params = [
            'client_id' => $this->clientId,
            'response_type' => 'code',
            'state' => $state,
        ];

        if ($this->redirectUri !== null) {
            $params['redirect_uri'] = $this->redirectUri;
        }

        if ($this->scopes !== []) {
            $params['scope'] = implode(' ', $this->scopes);
        }

        $params = array_merge($params, $this->getAdditionalAuthorizeParams());

        $separator = str_contains($this->getAuthorizeUrl(), '?') ? '&' : '?';

        return $this->getAuthorizeUrl() . $separator . http_build_query($params);
    }

    public function handleCallback(array $params): SocialUser
    {
        if (isset($params['error'])) {
            $description = $params['error_description'] ?? $params['error'];
            throw new \RuntimeException("Provider returned error: {$description}");
        }

        $code = $params['code'] ?? throw new \InvalidArgumentException('Missing authorization code');

        $tokenData = $this->exchangeCode($code);
        $accessToken = $tokenData['access_token'] ?? throw new \RuntimeException('No access_token in token response');
        $refreshToken = $tokenData['refresh_token'] ?? null;

        $userData = $this->fetchUserInfo($accessToken);

        return $this->mapUserData($userData, $accessToken, $refreshToken);
    }

    /**
     * Generate a cryptographically secure state parameter.
     */
    public static function generateState(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Validate that the returned state matches the expected state.
     */
    public static function validateState(string $expected, string $actual): bool
    {
        return hash_equals($expected, $actual);
    }

    /**
     * @param array<string> $scopes
     */
    public function setScopes(array $scopes): void
    {
        $this->scopes = $scopes;
    }

    /**
     * @return array<string, string>
     */
    protected function getAdditionalAuthorizeParams(): array
    {
        return [];
    }

    /**
     * @return array<string, mixed>
     */
    protected function exchangeCode(string $code): array
    {
        $params = [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
        ];

        if ($this->redirectUri !== null) {
            $params['redirect_uri'] = $this->redirectUri;
        }

        return $this->httpPost($this->getTokenUrl(), $params);
    }

    /**
     * @return array<string, mixed>
     */
    protected function fetchUserInfo(string $accessToken): array
    {
        return $this->httpGet($this->getUserInfoUrl(), $accessToken);
    }

    /**
     * @return array<string, mixed>
     */
    protected function httpPost(string $url, array $params): array
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/x-www-form-urlencoded\r\nAccept: application/json\r\n",
                'content' => http_build_query($params),
                'ignore_errors' => true,
            ],
        ]);

        $response = file_get_contents($url, false, $context);

        if ($response === false) {
            throw new \RuntimeException("HTTP POST failed: {$url}");
        }

        $data = json_decode($response, true, 512, JSON_THROW_ON_ERROR);

        if (!is_array($data)) {
            throw new \RuntimeException('Invalid JSON response');
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    protected function httpGet(string $url, string $accessToken): array
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => "Authorization: Bearer {$accessToken}\r\nAccept: application/json\r\n",
                'ignore_errors' => true,
            ],
        ]);

        $response = file_get_contents($url, false, $context);

        if ($response === false) {
            throw new \RuntimeException("HTTP GET failed: {$url}");
        }

        $data = json_decode($response, true, 512, JSON_THROW_ON_ERROR);

        if (!is_array($data)) {
            throw new \RuntimeException('Invalid JSON response');
        }

        return $data;
    }
}
