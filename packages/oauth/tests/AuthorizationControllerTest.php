<?php

declare(strict_types=1);

namespace Lattice\OAuth\Tests;

use Lattice\OAuth\AuthorizationController;
use Lattice\OAuth\Client;
use Lattice\OAuth\ScopeRegistry;
use Lattice\OAuth\Store\InMemoryAuthorizationCodeStore;
use Lattice\OAuth\Store\InMemoryClientStore;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AuthorizationControllerTest extends TestCase
{
    private AuthorizationController $controller;
    private InMemoryClientStore $clientStore;
    private InMemoryAuthorizationCodeStore $codeStore;
    private ScopeRegistry $scopeRegistry;

    protected function setUp(): void
    {
        $this->clientStore = new InMemoryClientStore();
        $this->codeStore = new InMemoryAuthorizationCodeStore();
        $this->scopeRegistry = new ScopeRegistry();
        $this->scopeRegistry->register('read', 'Read access');
        $this->scopeRegistry->register('write', 'Write access');

        $this->controller = new AuthorizationController(
            clientStore: $this->clientStore,
            codeStore: $this->codeStore,
            scopeRegistry: $this->scopeRegistry,
            codeTtl: 600,
        );

        $this->clientStore->register(new Client(
            id: 'app-1',
            secret: 'secret',
            name: 'Test App',
            redirectUris: ['https://app.test/callback'],
            grantTypes: ['authorization_code'],
            scopes: ['read', 'write'],
        ));
    }

    #[Test]
    public function it_validates_a_valid_authorization_request(): void
    {
        $result = $this->controller->validateAuthorizationRequest([
            'response_type' => 'code',
            'client_id' => 'app-1',
            'redirect_uri' => 'https://app.test/callback',
            'scope' => 'read',
            'state' => 'abc',
        ]);

        $this->assertSame('app-1', $result['client']->getId());
        $this->assertSame(['read'], $result['scopes']);
        $this->assertSame('abc', $result['state']);
        $this->assertSame('https://app.test/callback', $result['redirect_uri']);
    }

    #[Test]
    public function it_throws_for_missing_response_type(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing response_type');

        $this->controller->validateAuthorizationRequest([
            'client_id' => 'app-1',
        ]);
    }

    #[Test]
    public function it_throws_for_unsupported_response_type(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported response_type');

        $this->controller->validateAuthorizationRequest([
            'response_type' => 'token',
            'client_id' => 'app-1',
        ]);
    }

    #[Test]
    public function it_throws_for_unknown_client(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown client');

        $this->controller->validateAuthorizationRequest([
            'response_type' => 'code',
            'client_id' => 'unknown',
        ]);
    }

    #[Test]
    public function it_throws_for_invalid_redirect_uri(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid redirect_uri');

        $this->controller->validateAuthorizationRequest([
            'response_type' => 'code',
            'client_id' => 'app-1',
            'redirect_uri' => 'https://evil.test/callback',
        ]);
    }

    #[Test]
    public function it_uses_single_registered_redirect_uri_as_default(): void
    {
        $result = $this->controller->validateAuthorizationRequest([
            'response_type' => 'code',
            'client_id' => 'app-1',
            'scope' => 'read',
        ]);

        $this->assertSame('https://app.test/callback', $result['redirect_uri']);
    }

    #[Test]
    public function it_approves_and_generates_redirect_with_code(): void
    {
        $redirectUrl = $this->controller->approve(
            userId: 'user-1',
            clientId: 'app-1',
            scopes: ['read'],
            redirectUri: 'https://app.test/callback',
            state: 'my-state',
        );

        $parsed = parse_url($redirectUrl);
        parse_str($parsed['query'] ?? '', $query);

        $this->assertStringContainsString('https://app.test/callback', $redirectUrl);
        $this->assertArrayHasKey('code', $query);
        $this->assertSame('my-state', $query['state']);
        $this->assertSame(64, strlen($query['code']));

        // Verify the code was stored
        $storedCode = $this->codeStore->find($query['code']);
        $this->assertNotNull($storedCode);
        $this->assertSame('app-1', $storedCode->clientId);
        $this->assertSame('user-1', $storedCode->userId);
        $this->assertSame(['read'], $storedCode->scopes);
    }

    #[Test]
    public function it_stores_pkce_challenge_with_code(): void
    {
        $this->controller->approve(
            userId: 'user-1',
            clientId: 'app-1',
            scopes: ['read'],
            redirectUri: 'https://app.test/callback',
            codeChallenge: 'E9Melhoa2OwvFrEMTJguCHaoeK1t8URWbuGJSstw-cM',
            codeChallengeMethod: 'S256',
        );

        // Find the stored code
        // The code is random, so we need to check the store has a code with PKCE
        $allEmpty = true;
        // We'll approve again and capture the URL
        $url = $this->controller->approve(
            userId: 'user-1',
            clientId: 'app-1',
            scopes: ['read'],
            redirectUri: 'https://app.test/callback',
            codeChallenge: 'test-challenge',
            codeChallengeMethod: 'S256',
        );

        parse_str(parse_url($url, PHP_URL_QUERY) ?? '', $query);
        $stored = $this->codeStore->find($query['code']);

        $this->assertNotNull($stored);
        $this->assertSame('test-challenge', $stored->codeChallenge);
        $this->assertSame('S256', $stored->codeChallengeMethod);
        $this->assertTrue($stored->hasPkce());
    }

    #[Test]
    public function it_denies_and_returns_error_redirect(): void
    {
        $url = $this->controller->deny('https://app.test/callback', 'my-state');

        $parsed = parse_url($url);
        parse_str($parsed['query'] ?? '', $query);

        $this->assertSame('access_denied', $query['error']);
        $this->assertSame('my-state', $query['state']);
    }

    #[Test]
    public function it_generates_code_that_expires(): void
    {
        $url = $this->controller->approve(
            userId: 'user-1',
            clientId: 'app-1',
            scopes: ['read'],
            redirectUri: 'https://app.test/callback',
        );

        parse_str(parse_url($url, PHP_URL_QUERY) ?? '', $query);
        $stored = $this->codeStore->find($query['code']);

        $this->assertNotNull($stored);
        // Code should expire in the future (within 10 minutes + a small delta)
        $this->assertGreaterThan(new \DateTimeImmutable(), $stored->expiresAt);
        $this->assertLessThanOrEqual(new \DateTimeImmutable('+11 minutes'), $stored->expiresAt);
    }
}
