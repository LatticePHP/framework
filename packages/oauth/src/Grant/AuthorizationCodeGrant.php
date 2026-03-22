<?php

declare(strict_types=1);

namespace Lattice\OAuth\Grant;

use Lattice\OAuth\AuthorizationCodeStoreInterface;
use Lattice\OAuth\ClientInterface;
use Lattice\OAuth\PkceValidator;
use Lattice\OAuth\RefreshTokenStoreInterface;
use Lattice\OAuth\TokenResponse;

final class AuthorizationCodeGrant implements GrantHandlerInterface
{
    private readonly PkceValidator $pkceValidator;

    public function __construct(
        private readonly AuthorizationCodeStoreInterface $codeStore,
        private readonly RefreshTokenStoreInterface $refreshTokenStore,
        private readonly string $secret,
        private readonly int $accessTokenTtl = 3600,
        private readonly int $refreshTokenTtl = 86400,
    ) {
        $this->pkceValidator = new PkceValidator();
    }

    public function supports(string $grantType): bool
    {
        return $grantType === 'authorization_code';
    }

    public function handle(array $params, ClientInterface $client): TokenResponse
    {
        $code = $params['code'] ?? throw new \InvalidArgumentException('Missing authorization code');

        $authCode = $this->codeStore->find($code);

        if ($authCode === null) {
            throw new \InvalidArgumentException('Invalid authorization code');
        }

        if ($authCode->used) {
            throw new \InvalidArgumentException('Authorization code already used');
        }

        if ($authCode->expiresAt < new \DateTimeImmutable()) {
            throw new \InvalidArgumentException('Authorization code expired');
        }

        if ($authCode->clientId !== $client->getId()) {
            throw new \InvalidArgumentException('Client mismatch');
        }

        // Validate redirect_uri if it was provided in the original request
        if ($authCode->redirectUri !== null) {
            $redirectUri = $params['redirect_uri'] ?? null;
            if ($redirectUri !== $authCode->redirectUri) {
                throw new \InvalidArgumentException('Redirect URI mismatch');
            }
        }

        // Validate PKCE if code_challenge was present
        if ($authCode->hasPkce()) {
            $codeVerifier = $params['code_verifier'] ?? throw new \InvalidArgumentException('Missing code_verifier');
            if (!$this->pkceValidator->validate($codeVerifier, $authCode->codeChallenge, $authCode->codeChallengeMethod ?? 'S256')) {
                throw new \InvalidArgumentException('Invalid code_verifier');
            }
        }

        // Mark code as used
        $authCode->used = true;

        $scopes = $authCode->scopes;
        $accessToken = $this->generateAccessToken($authCode->userId, $scopes);
        $refreshToken = bin2hex(random_bytes(32));
        $familyId = bin2hex(random_bytes(16));

        $this->refreshTokenStore->store(
            token: $refreshToken,
            clientId: $client->getId(),
            userId: $authCode->userId,
            scopes: $scopes,
            expiresAt: new \DateTimeImmutable("+{$this->refreshTokenTtl} seconds"),
            familyId: $familyId,
        );

        return new TokenResponse(
            accessToken: $accessToken,
            tokenType: 'Bearer',
            expiresIn: $this->accessTokenTtl,
            refreshToken: $refreshToken,
            scope: implode(' ', $scopes),
        );
    }

    /**
     * @param array<string> $scopes
     */
    private function generateAccessToken(string|int $userId, array $scopes): string
    {
        $payload = [
            'sub' => (string) $userId,
            'type' => 'authorization_code',
            'scopes' => $scopes,
            'iat' => time(),
            'exp' => time() + $this->accessTokenTtl,
        ];

        $header = base64_encode(json_encode(['typ' => 'JWT', 'alg' => 'HS256']));
        $body = base64_encode(json_encode($payload));
        $signature = base64_encode(hash_hmac('sha256', "{$header}.{$body}", $this->secret, true));

        return "{$header}.{$body}.{$signature}";
    }
}
