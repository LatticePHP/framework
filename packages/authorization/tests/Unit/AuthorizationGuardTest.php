<?php

declare(strict_types=1);

namespace Lattice\Authorization\Tests\Unit;

use Lattice\Auth\Attributes\Authorize;
use Lattice\Auth\Attributes\Roles;
use Lattice\Auth\Attributes\Scopes;
use Lattice\Auth\Principal;
use Lattice\Authorization\AuthorizationGuard;
use Lattice\Authorization\Gate;
use Lattice\Authorization\PolicyRegistry;
use Lattice\Contracts\Context\ExecutionContextInterface;
use Lattice\Contracts\Context\ExecutionType;
use Lattice\Contracts\Context\PrincipalInterface;
use Lattice\Contracts\Pipeline\GuardInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AuthorizationGuardTest extends TestCase
{
    #[Test]
    public function it_implements_guard_interface(): void
    {
        $guard = new AuthorizationGuard(new Gate(), new PolicyRegistry());
        $this->assertInstanceOf(GuardInterface::class, $guard);
    }

    #[Test]
    public function it_allows_when_no_attributes_present(): void
    {
        $guard = new AuthorizationGuard(new Gate(), new PolicyRegistry());

        $context = $this->createContext(
            principal: new Principal(id: 1, type: 'user'),
            class: NoAttributesController::class,
            method: 'index',
        );

        $this->assertTrue($guard->canActivate($context));
    }

    #[Test]
    public function it_denies_when_authorize_ability_fails(): void
    {
        $gate = new Gate();
        $gate->define('manage-posts', fn () => false);

        $guard = new AuthorizationGuard($gate, new PolicyRegistry());

        $context = $this->createContext(
            principal: new Principal(id: 1, type: 'user'),
            class: AuthorizeController::class,
            method: 'update',
        );

        $this->assertFalse($guard->canActivate($context));
    }

    #[Test]
    public function it_allows_when_authorize_ability_passes(): void
    {
        $gate = new Gate();
        $gate->define('manage-posts', fn () => true);

        $guard = new AuthorizationGuard($gate, new PolicyRegistry());

        $context = $this->createContext(
            principal: new Principal(id: 1, type: 'user'),
            class: AuthorizeController::class,
            method: 'update',
        );

        $this->assertTrue($guard->canActivate($context));
    }

    #[Test]
    public function it_denies_when_required_scopes_missing(): void
    {
        $guard = new AuthorizationGuard(new Gate(), new PolicyRegistry());

        $context = $this->createContext(
            principal: new Principal(id: 1, type: 'user', scopes: ['read']),
            class: ScopedController::class,
            method: 'write',
        );

        $this->assertFalse($guard->canActivate($context));
    }

    #[Test]
    public function it_allows_when_required_scopes_present(): void
    {
        $guard = new AuthorizationGuard(new Gate(), new PolicyRegistry());

        $context = $this->createContext(
            principal: new Principal(id: 1, type: 'user', scopes: ['read', 'write']),
            class: ScopedController::class,
            method: 'write',
        );

        $this->assertTrue($guard->canActivate($context));
    }

    #[Test]
    public function it_denies_when_required_roles_missing(): void
    {
        $guard = new AuthorizationGuard(new Gate(), new PolicyRegistry());

        $context = $this->createContext(
            principal: new Principal(id: 1, type: 'user', roles: ['user']),
            class: RoledController::class,
            method: 'admin',
        );

        $this->assertFalse($guard->canActivate($context));
    }

    #[Test]
    public function it_allows_when_required_roles_present(): void
    {
        $guard = new AuthorizationGuard(new Gate(), new PolicyRegistry());

        $context = $this->createContext(
            principal: new Principal(id: 1, type: 'user', roles: ['admin']),
            class: RoledController::class,
            method: 'admin',
        );

        $this->assertTrue($guard->canActivate($context));
    }

    #[Test]
    public function it_denies_when_no_principal_and_attributes_present(): void
    {
        $guard = new AuthorizationGuard(new Gate(), new PolicyRegistry());

        $context = $this->createContext(
            principal: null,
            class: AuthorizeController::class,
            method: 'update',
        );

        $this->assertFalse($guard->canActivate($context));
    }

    private function createContext(
        ?PrincipalInterface $principal,
        string $class,
        string $method,
    ): ExecutionContextInterface {
        return new class($principal, $class, $method) implements ExecutionContextInterface {
            public function __construct(
                private readonly ?PrincipalInterface $principal,
                private readonly string $class,
                private readonly string $method,
            ) {}

            public function getType(): ExecutionType { return ExecutionType::Http; }
            public function getModule(): string { return 'test'; }
            public function getHandler(): string { return $this->class . '::' . $this->method; }
            public function getClass(): string { return $this->class; }
            public function getMethod(): string { return $this->method; }
            public function getCorrelationId(): string { return 'corr-1'; }
            public function getPrincipal(): ?PrincipalInterface { return $this->principal; }
        };
    }
}

// Test fixture classes
class NoAttributesController
{
    public function index(): void {}
}

class AuthorizeController
{
    #[Authorize('manage-posts')]
    public function update(): void {}
}

class ScopedController
{
    #[Scopes(['read', 'write'])]
    public function write(): void {}
}

class RoledController
{
    #[Roles(['admin'])]
    public function admin(): void {}
}
