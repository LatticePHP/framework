<?php

declare(strict_types=1);

namespace Lattice\Auth\Tests;

use Lattice\Auth\AuthManager;
use Lattice\Auth\Guard\AuthGuard;
use Lattice\Auth\Principal;
use Lattice\Contracts\Auth\AuthGuardInterface;
use Lattice\Contracts\Context\ExecutionContextInterface;
use Lattice\Contracts\Pipeline\GuardInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AuthGuardTest extends TestCase
{
    #[Test]
    public function it_implements_guard_interface(): void
    {
        $authManager = new AuthManager(defaultGuard: 'test');

        $guard = new AuthGuard($authManager);

        $this->assertInstanceOf(GuardInterface::class, $guard);
    }

    #[Test]
    public function it_activates_when_principal_is_present(): void
    {
        $principal = new Principal(id: 1);

        $context = $this->createMock(ExecutionContextInterface::class);
        $context->method('getPrincipal')->willReturn($principal);

        $authManager = new AuthManager(defaultGuard: 'test');
        $guard = new AuthGuard($authManager);

        $this->assertTrue($guard->canActivate($context));
    }

    #[Test]
    public function it_does_not_activate_when_principal_is_null(): void
    {
        $context = $this->createMock(ExecutionContextInterface::class);
        $context->method('getPrincipal')->willReturn(null);

        $authManager = new AuthManager(defaultGuard: 'test');
        $guard = new AuthGuard($authManager);

        $this->assertFalse($guard->canActivate($context));
    }
}
