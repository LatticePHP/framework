<?php

declare(strict_types=1);

namespace Lattice\Jwt\Tests;

use Lattice\Auth\Principal;
use Lattice\Contracts\Auth\AuthGuardInterface;
use Lattice\Contracts\Context\PrincipalInterface;
use Lattice\Jwt\JwtAuthGuard;
use Lattice\Jwt\JwtConfig;
use Lattice\Jwt\JwtEncoder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class JwtAuthGuardTest extends TestCase
{
    private JwtAuthGuard $guard;
    private JwtEncoder $encoder;
    private JwtConfig $config;

    protected function setUp(): void
    {
        $this->encoder = new JwtEncoder();
        $this->config = new JwtConfig(
            secret: 'test-secret-key-must-be-long-enough-for-hmac',
            algorithm: 'HS256',
            accessTokenTtl: 3600,
            refreshTokenTtl: 86400,
            issuer: 'lattice-test',
            audience: 'api-test',
        );

        $this->guard = new JwtAuthGuard($this->encoder, $this->config);
    }

    #[Test]
    public function it_implements_auth_guard_interface(): void
    {
        $this->assertInstanceOf(AuthGuardInterface::class, $this->guard);
    }

    #[Test]
    public function it_supports_jwt_type(): void
    {
        $this->assertTrue($this->guard->supports('jwt'));
        $this->assertFalse($this->guard->supports('api-key'));
        $this->assertFalse($this->guard->supports('session'));
    }

    #[Test]
    public function it_authenticates_valid_bearer_token(): void
    {
        $payload = [
            'sub' => 'user-123',
            'iss' => 'lattice-test',
            'aud' => 'api-test',
            'iat' => time(),
            'exp' => time() + 3600,
            'scopes' => ['read', 'write'],
            'roles' => ['admin'],
        ];

        $token = $this->encoder->encode($payload, $this->config->secret);

        $principal = $this->guard->authenticate(['authorization' => "Bearer {$token}"]);

        $this->assertInstanceOf(PrincipalInterface::class, $principal);
        $this->assertSame('user-123', $principal->getId());
        $this->assertSame(['read', 'write'], $principal->getScopes());
        $this->assertSame(['admin'], $principal->getRoles());
    }

    #[Test]
    public function it_returns_null_for_missing_authorization(): void
    {
        $principal = $this->guard->authenticate([]);

        $this->assertNull($principal);
    }

    #[Test]
    public function it_returns_null_for_non_bearer_token(): void
    {
        $principal = $this->guard->authenticate(['authorization' => 'Basic dXNlcjpwYXNz']);

        $this->assertNull($principal);
    }

    #[Test]
    public function it_returns_null_for_invalid_jwt(): void
    {
        $principal = $this->guard->authenticate(['authorization' => 'Bearer invalid.token.here']);

        $this->assertNull($principal);
    }

    #[Test]
    public function it_returns_null_for_expired_jwt(): void
    {
        $payload = [
            'sub' => 'user-123',
            'iss' => 'lattice-test',
            'aud' => 'api-test',
            'iat' => time() - 7200,
            'exp' => time() - 3600,
            'scopes' => [],
            'roles' => [],
        ];

        $token = $this->encoder->encode($payload, $this->config->secret);

        $principal = $this->guard->authenticate(['authorization' => "Bearer {$token}"]);

        $this->assertNull($principal);
    }

    #[Test]
    public function it_returns_null_for_wrong_secret(): void
    {
        $payload = [
            'sub' => 'user-123',
            'iss' => 'lattice-test',
            'aud' => 'api-test',
            'iat' => time(),
            'exp' => time() + 3600,
            'scopes' => [],
            'roles' => [],
        ];

        $wrongEncoder = new JwtEncoder();
        $token = $wrongEncoder->encode($payload, 'different-secret-key-that-is-long');

        $principal = $this->guard->authenticate(['authorization' => "Bearer {$token}"]);

        $this->assertNull($principal);
    }
}
