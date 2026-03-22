<?php

declare(strict_types=1);

namespace Lattice\Social\Provider;

use Lattice\Social\SocialProviderInterface;
use Lattice\Social\SocialUser;

final class GenericOAuthProvider implements SocialProviderInterface
{
    private const array DEFAULT_FIELD_MAPPING = [
        'id' => 'id',
        'email' => 'email',
        'name' => 'name',
        'avatar' => 'avatar',
    ];

    /** @var array<string, string> */
    private readonly array $fieldMapping;

    /**
     * @param array<string> $scopes
     * @param array<string, string> $fieldMapping Maps standard fields (id, email, name, avatar) to provider-specific field names
     */
    public function __construct(
        private readonly string $name,
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly string $authorizeUrl,
        private readonly string $tokenUrl,
        private readonly string $userInfoUrl,
        private readonly ?string $redirectUri = null,
        private readonly array $scopes = [],
        array $fieldMapping = [],
    ) {
        $this->fieldMapping = array_merge(self::DEFAULT_FIELD_MAPPING, $fieldMapping);
    }

    public function getName(): string
    {
        return $this->name;
    }

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

        $separator = str_contains($this->authorizeUrl, '?') ? '&' : '?';

        return $this->authorizeUrl . $separator . http_build_query($params);
    }

    public function handleCallback(array $params): SocialUser
    {
        $code = $params['code'] ?? throw new \InvalidArgumentException('Missing authorization code');

        $tokenData = $this->exchangeCode($code);
        $accessToken = $tokenData['access_token'] ?? throw new \RuntimeException('No access_token in token response');
        $refreshToken = $tokenData['refresh_token'] ?? null;

        $userData = $this->fetchUserInfo($accessToken);

        $idField = $this->fieldMapping['id'];
        $emailField = $this->fieldMapping['email'];
        $nameField = $this->fieldMapping['name'];
        $avatarField = $this->fieldMapping['avatar'];

        return new SocialUser(
            providerId: (string) ($userData[$idField] ?? ''),
            providerName: $this->name,
            email: $userData[$emailField] ?? null,
            name: $userData[$nameField] ?? null,
            avatar: $userData[$avatarField] ?? null,
            accessToken: $accessToken,
            refreshToken: $refreshToken,
            raw: $userData,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function exchangeCode(string $code): array
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

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/x-www-form-urlencoded\r\nAccept: application/json\r\n",
                'content' => http_build_query($params),
                'ignore_errors' => true,
            ],
        ]);

        $response = file_get_contents($this->tokenUrl, false, $context);

        if ($response === false) {
            throw new \RuntimeException("Failed to exchange authorization code at {$this->tokenUrl}");
        }

        $data = json_decode($response, true, 512, JSON_THROW_ON_ERROR);

        if (!is_array($data)) {
            throw new \RuntimeException('Invalid token response');
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchUserInfo(string $accessToken): array
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => "Authorization: Bearer {$accessToken}\r\nAccept: application/json\r\n",
                'ignore_errors' => true,
            ],
        ]);

        $response = file_get_contents($this->userInfoUrl, false, $context);

        if ($response === false) {
            throw new \RuntimeException("Failed to fetch user info from {$this->userInfoUrl}");
        }

        $data = json_decode($response, true, 512, JSON_THROW_ON_ERROR);

        if (!is_array($data)) {
            throw new \RuntimeException('Invalid user info response');
        }

        return $data;
    }
}
