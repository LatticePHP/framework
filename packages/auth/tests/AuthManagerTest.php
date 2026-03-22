<?php

declare(strict_types=1);

namespace Lattice\Auth\Tests;

use Lattice\Auth\AuthManager;
use Lattice\Auth\Principal;
use Lattice\Contracts\Auth\AuthGuardInterface;
use Lattice\Contracts\Context\PrincipalInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AuthManagerTest extends TestCase
{
    #[Test]
    public function it_registers_and_authenticates_with_guard(): void
    {
        $expectedPrincipal = new Principal(id: 1, scopes: ['read']);

        $guard = $this->createMock(AuthGuardInterface::class);
        $guard->method('authenticate')->willReturn($expectedPrincipal);

        $manager = new AuthManager(defaultGuard: 'token');
        $manager->registerGuard('token', $guard);

        $result = $manager->authenticate('token', ['token' => 'abc']);

        $this->assertSame($expectedPrincipal, $result);
    }

    #[Test]
    public function it_returns_null_when_guard_fails(): void
    {
        $guard = $this->createMock(AuthGuardInterface::class);
        $guard->method('authenticate')->willReturn(null);

        $manager = new AuthManager(defaultGuard: 'token');
        $manager->registerGuard('token', $guard);

        $result = $manager->authenticate('token', ['token' => 'invalid']);

        $this->assertNull($result);
    }

    #[Test]
    public function it_throws_for_unknown_guard(): void
    {
        $manager = new AuthManager(defaultGuard: 'token');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown auth guard: nonexistent');

        $manager->authenticate('nonexistent', []);
    }

    #[Test]
    public function it_returns_default_guard_name(): void
    {
        $manager = new AuthManager(defaultGuard: 'jwt');

        $this->assertSame('jwt', $manager->getDefaultGuard());
    }

    #[Test]
    public function it_allows_multiple_guards(): void
    {
        $principal1 = new Principal(id: 1);
        $principal2 = new Principal(id: 2);

        $guard1 = $this->createMock(AuthGuardInterface::class);
        $guard1->method('authenticate')->willReturn($principal1);

        $guard2 = $this->createMock(AuthGuardInterface::class);
        $guard2->method('authenticate')->willReturn($principal2);

        $manager = new AuthManager(defaultGuard: 'token');
        $manager->registerGuard('token', $guard1);
        $manager->registerGuard('api-key', $guard2);

        $this->assertSame($principal1, $manager->authenticate('token', []));
        $this->assertSame($principal2, $manager->authenticate('api-key', []));
    }
}
