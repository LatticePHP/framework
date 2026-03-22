<?php

declare(strict_types=1);

namespace Lattice\Contracts\Tests\Unit\Auth;

use Lattice\Contracts\Auth\AuthGuardInterface;
use Lattice\Contracts\Auth\PolicyInterface;
use Lattice\Contracts\Auth\TokenIssuerInterface;
use Lattice\Contracts\Auth\TokenPairInterface;
use Lattice\Contracts\Context\PrincipalInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class AuthContractsTest extends TestCase
{
    #[Test]
    public function authGuardInterfaceExists(): void
    {
        $this->assertTrue(interface_exists(AuthGuardInterface::class));
    }

    #[Test]
    public function authGuardInterfaceHasExpectedMethods(): void
    {
        $reflection = new ReflectionClass(AuthGuardInterface::class);

        $this->assertTrue($reflection->hasMethod('authenticate'));
        $this->assertTrue($reflection->hasMethod('supports'));

        $authenticateReturn = $reflection->getMethod('authenticate')->getReturnType();
        $this->assertTrue($authenticateReturn->allowsNull());
        $this->assertSame(PrincipalInterface::class, $authenticateReturn->getName());

        $this->assertSame('bool', $reflection->getMethod('supports')->getReturnType()->getName());
    }

    #[Test]
    public function authGuardCanBeImplemented(): void
    {
        $guard = new class implements AuthGuardInterface {
            public function authenticate(mixed $credentials): ?PrincipalInterface { return null; }
            public function supports(string $type): bool { return $type === 'bearer'; }
        };

        $this->assertNull($guard->authenticate('token'));
        $this->assertTrue($guard->supports('bearer'));
        $this->assertFalse($guard->supports('basic'));
    }

    #[Test]
    public function tokenPairInterfaceExists(): void
    {
        $this->assertTrue(interface_exists(TokenPairInterface::class));
    }

    #[Test]
    public function tokenPairInterfaceHasExpectedMethods(): void
    {
        $reflection = new ReflectionClass(TokenPairInterface::class);

        $expectedMethods = [
            'getAccessToken' => 'string',
            'getRefreshToken' => 'string',
            'getExpiresIn' => 'int',
            'getTokenType' => 'string',
        ];

        foreach ($expectedMethods as $methodName => $returnType) {
            $this->assertTrue($reflection->hasMethod($methodName), "Missing method: $methodName");
            $this->assertSame(
                $returnType,
                $reflection->getMethod($methodName)->getReturnType()->getName(),
                "Wrong return type for $methodName"
            );
        }
    }

    #[Test]
    public function tokenPairCanBeImplemented(): void
    {
        $pair = new class implements TokenPairInterface {
            public function getAccessToken(): string { return 'access-token'; }
            public function getRefreshToken(): string { return 'refresh-token'; }
            public function getExpiresIn(): int { return 3600; }
            public function getTokenType(): string { return 'Bearer'; }
        };

        $this->assertSame('access-token', $pair->getAccessToken());
        $this->assertSame('refresh-token', $pair->getRefreshToken());
        $this->assertSame(3600, $pair->getExpiresIn());
        $this->assertSame('Bearer', $pair->getTokenType());
    }

    #[Test]
    public function tokenIssuerInterfaceExists(): void
    {
        $this->assertTrue(interface_exists(TokenIssuerInterface::class));
    }

    #[Test]
    public function tokenIssuerInterfaceHasExpectedMethods(): void
    {
        $reflection = new ReflectionClass(TokenIssuerInterface::class);

        $this->assertTrue($reflection->hasMethod('issueAccessToken'));
        $this->assertTrue($reflection->hasMethod('refreshAccessToken'));
        $this->assertTrue($reflection->hasMethod('revokeRefreshToken'));

        $this->assertSame(
            TokenPairInterface::class,
            $reflection->getMethod('issueAccessToken')->getReturnType()->getName()
        );
        $this->assertSame(
            TokenPairInterface::class,
            $reflection->getMethod('refreshAccessToken')->getReturnType()->getName()
        );
        $this->assertSame(
            'void',
            $reflection->getMethod('revokeRefreshToken')->getReturnType()->getName()
        );
    }

    #[Test]
    public function policyInterfaceExists(): void
    {
        $this->assertTrue(interface_exists(PolicyInterface::class));
    }

    #[Test]
    public function policyInterfaceHasCanMethod(): void
    {
        $reflection = new ReflectionClass(PolicyInterface::class);

        $this->assertTrue($reflection->hasMethod('can'));

        $method = $reflection->getMethod('can');
        $this->assertSame('bool', $method->getReturnType()->getName());

        $params = $method->getParameters();
        $this->assertCount(3, $params);
        $this->assertSame(PrincipalInterface::class, $params[0]->getType()->getName());
        $this->assertSame('string', $params[1]->getType()->getName());
        $this->assertSame('mixed', $params[2]->getType()->getName());
    }

    #[Test]
    public function policyCanBeImplemented(): void
    {
        $policy = new class implements PolicyInterface {
            public function can(PrincipalInterface $principal, string $ability, mixed $subject = null): bool
            {
                return $principal->hasRole('admin');
            }
        };

        $admin = $this->createMock(PrincipalInterface::class);
        $admin->method('hasRole')->with('admin')->willReturn(true);

        $user = $this->createMock(PrincipalInterface::class);
        $user->method('hasRole')->with('admin')->willReturn(false);

        $this->assertTrue($policy->can($admin, 'delete'));
        $this->assertFalse($policy->can($user, 'delete'));
    }
}
