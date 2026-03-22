<?php

declare(strict_types=1);

namespace Lattice\Auth\Tests;

use Lattice\Auth\AuthorizationChecker;
use Lattice\Auth\Principal;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AuthorizationCheckerTest extends TestCase
{
    private AuthorizationChecker $checker;

    protected function setUp(): void
    {
        $this->checker = new AuthorizationChecker();
    }

    #[Test]
    public function check_scopes_passes_when_all_required_scopes_present(): void
    {
        $principal = new Principal(id: 1, scopes: ['read', 'write', 'admin']);

        $this->assertTrue($this->checker->checkScopes($principal, ['read', 'write']));
    }

    #[Test]
    public function check_scopes_fails_when_missing_required_scope(): void
    {
        $principal = new Principal(id: 1, scopes: ['read']);

        $this->assertFalse($this->checker->checkScopes($principal, ['read', 'write']));
    }

    #[Test]
    public function check_scopes_passes_with_empty_required(): void
    {
        $principal = new Principal(id: 1);

        $this->assertTrue($this->checker->checkScopes($principal, []));
    }

    #[Test]
    public function check_roles_passes_when_all_required_roles_present(): void
    {
        $principal = new Principal(id: 1, roles: ['admin', 'editor']);

        $this->assertTrue($this->checker->checkRoles($principal, ['admin']));
    }

    #[Test]
    public function check_roles_fails_when_missing_required_role(): void
    {
        $principal = new Principal(id: 1, roles: ['editor']);

        $this->assertFalse($this->checker->checkRoles($principal, ['admin']));
    }

    #[Test]
    public function check_roles_passes_with_empty_required(): void
    {
        $principal = new Principal(id: 1);

        $this->assertTrue($this->checker->checkRoles($principal, []));
    }

    #[Test]
    public function check_ability_delegates_to_registered_policy(): void
    {
        $principal = new Principal(id: 1, roles: ['admin']);

        $this->checker->registerAbility('delete', function ($p, $subject) {
            return $p->hasRole('admin');
        });

        $this->assertTrue($this->checker->checkAbility($principal, 'delete'));
    }

    #[Test]
    public function check_ability_returns_false_for_unregistered_ability(): void
    {
        $principal = new Principal(id: 1);

        $this->assertFalse($this->checker->checkAbility($principal, 'unknown'));
    }

    #[Test]
    public function check_ability_passes_subject_to_callback(): void
    {
        $principal = new Principal(id: 1);

        $this->checker->registerAbility('edit', function ($p, $subject) {
            return $subject['owner_id'] === $p->getId();
        });

        $this->assertTrue($this->checker->checkAbility($principal, 'edit', ['owner_id' => 1]));
        $this->assertFalse($this->checker->checkAbility($principal, 'edit', ['owner_id' => 2]));
    }
}
