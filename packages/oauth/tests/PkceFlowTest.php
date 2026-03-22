<?php

declare(strict_types=1);

namespace Lattice\OAuth\Tests;

use Lattice\OAuth\AuthorizationController;
use Lattice\OAuth\Client;
use Lattice\OAuth\Grant\AuthorizationCodeGrant;
use Lattice\OAuth\OAuthServer;
use Lattice\OAuth\PkceValidator;
use Lattice\OAuth\ScopeRegistry;
use Lattice\OAuth\Store\InMemoryAuthorizationCodeStore;
use Lattice\OAuth\Store\InMemoryClientStore;
use Lattice\OAuth\Store\InMemoryRefreshTokenStore;
use Lattice\OAuth\TokenController;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PkceFlowTest extends TestCase
{
    private AuthorizationController $authzController;
    private TokenController $tokenController;
    private InMemoryClientStore $clientStore;
    private InMemoryAuthorizationCodeStore $codeStore;
    private PkceValidator $pkce;

    protected function setUp(): void
    {
        $this->clientStore = new InMemoryClientStore();
        $this->codeStore = new InMemoryAuthorizationCodeStore();
        $refreshStore = new InMemoryRefreshTokenStore();
        $this->pkce = new PkceValidator();

        $scopeRegistry = new ScopeRegistry();
        $scopeRegistry->register('read', 'Read access');
        $scopeRegistry->register('write', 'Write access');

        $this->authzController = new AuthorizationController(
            clientStore: $this->clientStore,
            codeStore: $this->codeStore,
            scopeRegistry: $scopeRegistry,
        );

        $secret = 'test-secret';
        $server = new OAuthServer(
            clientStore: $this->clientStore,
            grantHandlers: [
                new AuthorizationCodeGrant(
                    codeStore: $this->codeStore,
                    refreshTokenStore: $refreshStore,
                    secret: $secret,
                ),
            ],
        );

        $this->tokenController = new TokenController($server);

        $this->clientStore->register(new Client(
            id: 'app-1',
            secret: 'app-secret',
            name: 'PKCE App',
            redirectUris: ['https://app.test/callback'],
            grantTypes: ['authorization_code'],
            scopes: ['read', 'write'],
        ));
    }

    #[Test]
    public function it_completes_full_authorization_code_pkce_flow(): void
    {
        // Step 1: Generate PKCE verifier and challenge
        $codeVerifier = $this->pkce->generateVerifier(64);
        $codeChallenge = $this->pkce->computeS256Challenge($codeVerifier);

        // Step 2: Validate authorization request
        $authRequest = $this->authzController->validateAuthorizationRequest([
            'response_type' => 'code',
            'client_id' => 'app-1',
            'redirect_uri' => 'https://app.test/callback',
            'scope' => 'read',
            'state' => 'state-123',
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
        ]);

        $this->assertSame(['read'], $authRequest['scopes']);

        // Step 3: User approves — generate authorization code
        $redirectUrl = $this->authzController->approve(
            userId: 'user-42',
            clientId: 'app-1',
            scopes: $authRequest['scopes'],
            redirectUri: $authRequest['redirect_uri'],
            codeChallenge: $codeChallenge,
            codeChallengeMethod: 'S256',
            state: $authRequest['state'],
        );

        // Extract code from redirect URL
        parse_str(parse_url($redirectUrl, PHP_URL_QUERY) ?? '', $query);
        $code = $query['code'];
        $this->assertSame('state-123', $query['state']);

        // Step 4: Exchange code for tokens with PKCE verifier
        $result = $this->tokenController->handleTokenRequest([
            'grant_type' => 'authorization_code',
            'client_id' => 'app-1',
            'client_secret' => 'app-secret',
            'code' => $code,
            'redirect_uri' => 'https://app.test/callback',
            'code_verifier' => $codeVerifier,
        ]);

        $this->assertArrayHasKey('access_token', $result);
        $this->assertArrayHasKey('refresh_token', $result);
        $this->assertSame('Bearer', $result['token_type']);
        $this->assertSame('read', $result['scope']);
    }

    #[Test]
    public function it_rejects_invalid_code_verifier(): void
    {
        $codeVerifier = $this->pkce->generateVerifier(64);
        $codeChallenge = $this->pkce->computeS256Challenge($codeVerifier);

        $redirectUrl = $this->authzController->approve(
            userId: 'user-42',
            clientId: 'app-1',
            scopes: ['read'],
            redirectUri: 'https://app.test/callback',
            codeChallenge: $codeChallenge,
            codeChallengeMethod: 'S256',
        );

        parse_str(parse_url($redirectUrl, PHP_URL_QUERY) ?? '', $query);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid code_verifier');

        $this->tokenController->handleTokenRequest([
            'grant_type' => 'authorization_code',
            'client_id' => 'app-1',
            'client_secret' => 'app-secret',
            'code' => $query['code'],
            'redirect_uri' => 'https://app.test/callback',
            'code_verifier' => 'wrong-verifier-value-that-does-not-match',
        ]);
    }

    #[Test]
    public function it_requires_code_verifier_when_challenge_was_sent(): void
    {
        $codeVerifier = $this->pkce->generateVerifier(64);
        $codeChallenge = $this->pkce->computeS256Challenge($codeVerifier);

        $redirectUrl = $this->authzController->approve(
            userId: 'user-42',
            clientId: 'app-1',
            scopes: ['read'],
            redirectUri: 'https://app.test/callback',
            codeChallenge: $codeChallenge,
            codeChallengeMethod: 'S256',
        );

        parse_str(parse_url($redirectUrl, PHP_URL_QUERY) ?? '', $query);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing code_verifier');

        $this->tokenController->handleTokenRequest([
            'grant_type' => 'authorization_code',
            'client_id' => 'app-1',
            'client_secret' => 'app-secret',
            'code' => $query['code'],
            'redirect_uri' => 'https://app.test/callback',
            // No code_verifier provided
        ]);
    }

    #[Test]
    public function it_prevents_authorization_code_reuse(): void
    {
        $redirectUrl = $this->authzController->approve(
            userId: 'user-42',
            clientId: 'app-1',
            scopes: ['read'],
            redirectUri: 'https://app.test/callback',
        );

        parse_str(parse_url($redirectUrl, PHP_URL_QUERY) ?? '', $query);
        $code = $query['code'];

        // First exchange succeeds
        $this->tokenController->handleTokenRequest([
            'grant_type' => 'authorization_code',
            'client_id' => 'app-1',
            'client_secret' => 'app-secret',
            'code' => $code,
            'redirect_uri' => 'https://app.test/callback',
        ]);

        // Second exchange fails
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Authorization code already used');

        $this->tokenController->handleTokenRequest([
            'grant_type' => 'authorization_code',
            'client_id' => 'app-1',
            'client_secret' => 'app-secret',
            'code' => $code,
            'redirect_uri' => 'https://app.test/callback',
        ]);
    }
}
