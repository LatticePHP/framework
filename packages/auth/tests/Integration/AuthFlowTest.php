<?php

declare(strict_types=1);

namespace Lattice\Auth\Tests\Integration;

use Lattice\Auth\Hashing\HashManager;
use Lattice\Auth\JwtAuthenticationGuard;
use Lattice\Auth\Principal;
use Lattice\Http\HttpExecutionContext;
use Lattice\Http\Request;
use Lattice\Jwt\JwtConfig;
use Lattice\Jwt\JwtEncoder;
use Lattice\Jwt\JwtTokenIssuer;
use Lattice\Jwt\Store\InMemoryRefreshTokenStore;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AuthFlowTest extends TestCase
{
    private JwtEncoder $encoder;
    private JwtConfig $config;
    private JwtTokenIssuer $issuer;
    private InMemoryRefreshTokenStore $refreshStore;
    private HashManager $hasher;
    private JwtAuthenticationGuard $guard;

    protected function setUp(): void
    {
        $this->encoder = new JwtEncoder();
        $this->config = new JwtConfig(
            secret: 'test-secret-key-for-jwt-signing-must-be-long',
            algorithm: 'HS256',
            accessTokenTtl: 3600,
            refreshTokenTtl: 86400,
            issuer: 'lattice-test',
            audience: 'api-test',
        );
        $this->refreshStore = new InMemoryRefreshTokenStore();
        $this->issuer = new JwtTokenIssuer(
            encoder: $this->encoder,
            refreshTokenStore: $this->refreshStore,
            config: $this->config,
        );
        $this->hasher = new HashManager();
        $this->guard = new JwtAuthenticationGuard(
            encoder: $this->encoder,
            config: $this->config,
        );
    }

    #[Test]
    public function test_login_flow_issues_valid_token_pair(): void
    {
        // Simulate a user with hashed password
        $hashedPassword = $this->hasher->make('secret123');

        // Verify password check works
        $this->assertTrue($this->hasher->check('secret123', $hashedPassword));
        $this->assertFalse($this->hasher->check('wrong-password', $hashedPassword));

        // Issue tokens for the authenticated user
        $principal = new Principal(
            id: '42',
            type: 'user',
            roles: ['admin'],
        );

        $tokenPair = $this->issuer->issueAccessToken($principal);

        $this->assertNotEmpty($tokenPair->getAccessToken());
        $this->assertNotEmpty($tokenPair->getRefreshToken());
        $this->assertSame('Bearer', $tokenPair->getTokenType());
        $this->assertSame(3600, $tokenPair->getExpiresIn());

        // Verify the access token decodes correctly
        $claims = $this->encoder->decode(
            $tokenPair->getAccessToken(),
            $this->config->secret,
            $this->config->algorithm,
        );

        $this->assertSame('42', $claims['sub']);
        $this->assertSame('lattice-test', $claims['iss']);
        $this->assertSame('api-test', $claims['aud']);
        $this->assertContains('admin', $claims['roles']);
    }

    #[Test]
    public function test_login_with_wrong_password_is_rejected(): void
    {
        $hashedPassword = $this->hasher->make('correct-password');

        $this->assertFalse($this->hasher->check('wrong-password', $hashedPassword));
    }

    #[Test]
    public function test_register_flow_hashes_password_and_issues_tokens(): void
    {
        // Simulate registration: hash password
        $plainPassword = 'new-user-password';
        $hashedPassword = $this->hasher->make($plainPassword);

        // Verify the hash works
        $this->assertTrue($this->hasher->check($plainPassword, $hashedPassword));
        $this->assertNotSame($plainPassword, $hashedPassword);

        // Issue tokens for the newly registered user
        $principal = new Principal(
            id: '99',
            type: 'user',
            roles: ['user'],
        );

        $tokenPair = $this->issuer->issueAccessToken($principal);

        $this->assertNotEmpty($tokenPair->getAccessToken());
        $this->assertNotEmpty($tokenPair->getRefreshToken());

        // Verify token contains correct subject
        $claims = $this->encoder->decode(
            $tokenPair->getAccessToken(),
            $this->config->secret,
            $this->config->algorithm,
        );

        $this->assertSame('99', $claims['sub']);
        $this->assertContains('user', $claims['roles']);
    }

    #[Test]
    public function test_me_endpoint_with_valid_token_sets_principal(): void
    {
        // Issue a token
        $principal = new Principal(
            id: '42',
            type: 'user',
            roles: ['admin'],
            scopes: ['read', 'write'],
        );

        $tokenPair = $this->issuer->issueAccessToken($principal, ['read', 'write']);

        // Build a request with the token
        $request = new Request(
            method: 'GET',
            uri: '/api/auth/me',
            headers: ['Authorization' => 'Bearer ' . $tokenPair->getAccessToken()],
        );

        $context = new HttpExecutionContext(
            request: $request,
            module: 'auth',
            controllerClass: 'Lattice\Auth\Http\AuthController',
            methodName: 'me',
        );

        // Guard should activate and set the principal
        $result = $this->guard->canActivate($context);

        $this->assertTrue($result);
        $this->assertNotNull($context->getPrincipal());
        $this->assertSame('42', (string) $context->getPrincipal()->getId());
        $this->assertContains('admin', $context->getPrincipal()->getRoles());
        $this->assertContains('read', $context->getPrincipal()->getScopes());
    }

    #[Test]
    public function test_me_endpoint_without_token_returns_unauthorized(): void
    {
        $request = new Request(
            method: 'GET',
            uri: '/api/auth/me',
            headers: [],
        );

        $context = new HttpExecutionContext(
            request: $request,
            module: 'auth',
            controllerClass: 'Lattice\Auth\Http\AuthController',
            methodName: 'me',
        );

        $this->expectException(\Lattice\Http\Exception\UnauthorizedException::class);
        $this->guard->canActivate($context);
    }

    #[Test]
    public function test_me_endpoint_with_invalid_token_returns_unauthorized(): void
    {
        $request = new Request(
            method: 'GET',
            uri: '/api/auth/me',
            headers: ['Authorization' => 'Bearer invalid.token.here'],
        );

        $context = new HttpExecutionContext(
            request: $request,
            module: 'auth',
            controllerClass: 'Lattice\Auth\Http\AuthController',
            methodName: 'me',
        );

        $this->expectException(\Lattice\Http\Exception\UnauthorizedException::class);
        $this->guard->canActivate($context);
    }

    #[Test]
    public function test_me_endpoint_with_expired_token_returns_unauthorized(): void
    {
        // Create a config with 0 TTL to make token immediately expired
        $expiredConfig = new JwtConfig(
            secret: 'test-secret-key-for-jwt-signing-must-be-long',
            algorithm: 'HS256',
            accessTokenTtl: -1, // Already expired
            refreshTokenTtl: 86400,
            issuer: 'lattice-test',
            audience: 'api-test',
        );

        $expiredIssuer = new JwtTokenIssuer(
            encoder: $this->encoder,
            refreshTokenStore: $this->refreshStore,
            config: $expiredConfig,
        );

        $principal = new Principal(id: '42', type: 'user', roles: ['admin']);
        $tokenPair = $expiredIssuer->issueAccessToken($principal);

        $request = new Request(
            method: 'GET',
            uri: '/api/auth/me',
            headers: ['Authorization' => 'Bearer ' . $tokenPair->getAccessToken()],
        );

        $context = new HttpExecutionContext(
            request: $request,
            module: 'auth',
            controllerClass: 'Lattice\Auth\Http\AuthController',
            methodName: 'me',
        );

        $this->expectException(\Lattice\Http\Exception\UnauthorizedException::class);
        $this->guard->canActivate($context);
    }

    #[Test]
    public function test_refresh_token_returns_new_token_pair(): void
    {
        $principal = new Principal(
            id: '42',
            type: 'user',
            roles: ['admin'],
        );

        $originalPair = $this->issuer->issueAccessToken($principal);

        // Refresh should return a new token pair
        $newPair = $this->issuer->refreshAccessToken($originalPair->getRefreshToken());

        $this->assertNotEmpty($newPair->getAccessToken());
        $this->assertNotEmpty($newPair->getRefreshToken());
        $this->assertSame('Bearer', $newPair->getTokenType());

        // New access token should be different from original
        $this->assertNotSame($originalPair->getAccessToken(), $newPair->getAccessToken());

        // New refresh token should be different (rotation)
        $this->assertNotSame($originalPair->getRefreshToken(), $newPair->getRefreshToken());

        // New access token should decode with same subject
        $claims = $this->encoder->decode(
            $newPair->getAccessToken(),
            $this->config->secret,
            $this->config->algorithm,
        );

        $this->assertSame('42', $claims['sub']);
    }

    #[Test]
    public function test_refresh_token_rotation_revokes_old_token(): void
    {
        $principal = new Principal(id: '42', type: 'user', roles: ['admin']);
        $originalPair = $this->issuer->issueAccessToken($principal);

        // Use the refresh token once
        $this->issuer->refreshAccessToken($originalPair->getRefreshToken());

        // Trying to use the same refresh token again should fail
        $this->expectException(\Throwable::class);
        $this->issuer->refreshAccessToken($originalPair->getRefreshToken());
    }

    #[Test]
    public function test_invalid_refresh_token_throws(): void
    {
        $this->expectException(\Throwable::class);
        $this->issuer->refreshAccessToken('nonexistent-refresh-token');
    }

    #[Test]
    public function test_guard_with_malformed_authorization_header(): void
    {
        $request = new Request(
            method: 'GET',
            uri: '/api/auth/me',
            headers: ['Authorization' => 'Basic dXNlcjpwYXNz'],
        );

        $context = new HttpExecutionContext(
            request: $request,
            module: 'auth',
            controllerClass: 'Lattice\Auth\Http\AuthController',
            methodName: 'me',
        );

        $this->expectException(\Lattice\Http\Exception\UnauthorizedException::class);
        $this->guard->canActivate($context);
    }

    #[Test]
    public function test_guard_preserves_all_jwt_claims_in_principal(): void
    {
        $principal = new Principal(
            id: '42',
            type: 'user',
            roles: ['admin', 'editor'],
            scopes: ['read', 'write', 'delete'],
        );

        $tokenPair = $this->issuer->issueAccessToken($principal, ['read', 'write', 'delete']);

        $request = new Request(
            method: 'GET',
            uri: '/api/auth/me',
            headers: ['Authorization' => 'Bearer ' . $tokenPair->getAccessToken()],
        );

        $context = new HttpExecutionContext(
            request: $request,
            module: 'auth',
            controllerClass: 'Lattice\Auth\Http\AuthController',
            methodName: 'me',
        );

        $this->guard->canActivate($context);

        $restoredPrincipal = $context->getPrincipal();
        $this->assertNotNull($restoredPrincipal);
        $this->assertSame('42', (string) $restoredPrincipal->getId());
        $this->assertContains('admin', $restoredPrincipal->getRoles());
        $this->assertContains('editor', $restoredPrincipal->getRoles());
        $this->assertContains('read', $restoredPrincipal->getScopes());
        $this->assertContains('write', $restoredPrincipal->getScopes());
        $this->assertContains('delete', $restoredPrincipal->getScopes());
    }

    #[Test]
    public function test_hash_manager_bcrypt_round_trip(): void
    {
        $password = 'my-secure-password-123!@#';
        $hash = $this->hasher->make($password);

        $this->assertTrue($this->hasher->check($password, $hash));
        $this->assertFalse($this->hasher->check('different-password', $hash));

        // Hashing same password twice should produce different hashes (salt)
        $hash2 = $this->hasher->make($password);
        $this->assertNotSame($hash, $hash2);
        $this->assertTrue($this->hasher->check($password, $hash2));
    }

    #[Test]
    public function test_full_auth_lifecycle(): void
    {
        // 1. User registers - password is hashed
        $plainPassword = 'register-password';
        $hashedPassword = $this->hasher->make($plainPassword);

        // 2. User logs in - password is verified
        $this->assertTrue($this->hasher->check($plainPassword, $hashedPassword));

        // 3. Tokens are issued
        $principal = new Principal(id: '1', type: 'user', roles: ['user']);
        $tokenPair = $this->issuer->issueAccessToken($principal);

        // 4. User makes authenticated request - guard validates
        $request = new Request(
            method: 'GET',
            uri: '/api/auth/me',
            headers: ['Authorization' => 'Bearer ' . $tokenPair->getAccessToken()],
        );

        $context = new HttpExecutionContext(
            request: $request,
            module: 'auth',
            controllerClass: 'Lattice\Auth\Http\AuthController',
            methodName: 'me',
        );

        $this->assertTrue($this->guard->canActivate($context));
        $this->assertSame('1', (string) $context->getPrincipal()->getId());

        // 5. Token is refreshed
        $newPair = $this->issuer->refreshAccessToken($tokenPair->getRefreshToken());
        $this->assertNotSame($tokenPair->getAccessToken(), $newPair->getAccessToken());

        // 6. New token works
        $request2 = new Request(
            method: 'GET',
            uri: '/api/auth/me',
            headers: ['Authorization' => 'Bearer ' . $newPair->getAccessToken()],
        );

        $context2 = new HttpExecutionContext(
            request: $request2,
            module: 'auth',
            controllerClass: 'Lattice\Auth\Http\AuthController',
            methodName: 'me',
        );

        $this->assertTrue($this->guard->canActivate($context2));
        $this->assertSame('1', (string) $context2->getPrincipal()->getId());

        // 7. Old refresh token no longer works (rotation)
        $this->expectException(\Throwable::class);
        $this->issuer->refreshAccessToken($tokenPair->getRefreshToken());
    }
}
