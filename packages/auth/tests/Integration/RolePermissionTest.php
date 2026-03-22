<?php

declare(strict_types=1);

namespace Lattice\Auth\Tests\Integration;

use Lattice\Auth\Attributes\Can;
use Lattice\Auth\Attributes\Policy as PolicyAttribute;
use Lattice\Auth\Models\Permission;
use Lattice\Auth\Models\Role;
use Lattice\Auth\Models\User;
use Lattice\Auth\Principal;
use Lattice\Auth\Traits\HasPermissions;
use Lattice\Auth\Traits\HasRoles;
use Lattice\Authorization\Exceptions\ForbiddenException;
use Lattice\Authorization\Gate;
use Lattice\Authorization\PolicyRegistry;
use Lattice\Authorization\ResourcePolicy;
use Lattice\Contracts\Auth\PolicyInterface;
use Lattice\Contracts\Context\PrincipalInterface;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Integration tests for the RBAC/ABAC authorization system.
 *
 * NOTE: These tests verify the authorization logic in isolation (traits, gate,
 * policies, attributes) without requiring a live database. DB-backed Eloquent
 * relationships are tested indirectly through the trait method contracts; full
 * Eloquent integration tests require a database fixture and are in a separate suite.
 */
final class RolePermissionTest extends TestCase
{
    // -----------------------------------------------------------------------
    // Gate tests
    // -----------------------------------------------------------------------

    public function test_gate_define_and_allows(): void
    {
        $gate = new Gate();
        $principal = new Principal(id: 1, roles: ['editor']);

        $gate->define('edit-post', fn (PrincipalInterface $p) => $p->hasRole('editor'));

        $this->assertTrue($gate->allows($principal, 'edit-post'));
        $this->assertFalse($gate->denies($principal, 'edit-post'));
    }

    public function test_gate_denies_undefined_ability(): void
    {
        $gate = new Gate();
        $principal = new Principal(id: 1);

        $this->assertFalse($gate->allows($principal, 'nonexistent'));
        $this->assertTrue($gate->denies($principal, 'nonexistent'));
    }

    public function test_gate_denies_when_no_principal(): void
    {
        $gate = new Gate();
        $gate->define('edit-post', fn (PrincipalInterface $p) => true);

        $this->assertFalse($gate->allows(null, 'edit-post'));
    }

    public function test_gate_authorize_throws_on_denial(): void
    {
        $gate = new Gate();
        $principal = new Principal(id: 1);

        $gate->define('admin-only', fn (PrincipalInterface $p) => $p->hasRole('admin'));

        $this->expectException(ForbiddenException::class);
        $this->expectExceptionMessage('Unauthorized to perform [admin-only]');

        $gate->authorize($principal, 'admin-only');
    }

    public function test_gate_authorize_passes_when_allowed(): void
    {
        $gate = new Gate();
        $principal = new Principal(id: 1, roles: ['admin']);

        $gate->define('admin-only', fn (PrincipalInterface $p) => $p->hasRole('admin'));

        // Should not throw
        $gate->authorize($principal, 'admin-only');
        $this->assertTrue(true); // reached
    }

    public function test_gate_before_callback_grants_access(): void
    {
        $gate = new Gate();
        $superAdmin = new Principal(id: 1, roles: ['super-admin']);

        // Before callback: super-admin bypasses everything
        $gate->before(function (PrincipalInterface $p, string $ability): ?bool {
            if ($p->hasRole('super-admin')) {
                return true;
            }
            return null; // continue to normal check
        });

        // No ability defined, but super-admin should still pass
        $this->assertTrue($gate->allows($superAdmin, 'anything'));
    }

    public function test_gate_before_callback_denies_access(): void
    {
        $gate = new Gate();
        $banned = new Principal(id: 1, roles: ['banned']);

        $gate->before(function (PrincipalInterface $p, string $ability): ?bool {
            if ($p->hasRole('banned')) {
                return false;
            }
            return null;
        });

        $gate->define('view-post', fn (PrincipalInterface $p) => true);

        $this->assertFalse($gate->allows($banned, 'view-post'));
    }

    public function test_gate_before_null_falls_through(): void
    {
        $gate = new Gate();
        $user = new Principal(id: 1, roles: ['editor']);

        $gate->before(fn (PrincipalInterface $p, string $ability): ?bool => null);

        $gate->define('edit', fn (PrincipalInterface $p) => $p->hasRole('editor'));

        $this->assertTrue($gate->allows($user, 'edit'));
    }

    public function test_gate_for_user_scoping(): void
    {
        $gate = new Gate();
        $admin = new Principal(id: 1, roles: ['admin']);

        $gate->define('manage', fn (PrincipalInterface $p) => $p->hasRole('admin'));

        $scopedGate = $gate->forUser($admin);

        // Allows with null principal uses the scoped user
        $this->assertTrue($scopedGate->allows(null, 'manage'));
    }

    public function test_gate_has_and_abilities(): void
    {
        $gate = new Gate();
        $gate->define('read', fn () => true);
        $gate->define('write', fn () => true);

        $this->assertTrue($gate->has('read'));
        $this->assertFalse($gate->has('delete'));
        $this->assertEqualsCanonicalizing(['read', 'write'], $gate->abilities());
    }

    public function test_gate_allows_with_extra_args(): void
    {
        $gate = new Gate();
        $principal = new Principal(id: 1);

        $gate->define('edit-post', function (PrincipalInterface $p, int $postOwnerId): bool {
            return $p->getId() === $postOwnerId;
        });

        $this->assertTrue($gate->allows($principal, 'edit-post', 1));
        $this->assertFalse($gate->allows($principal, 'edit-post', 2));
    }

    // -----------------------------------------------------------------------
    // PolicyRegistry tests
    // -----------------------------------------------------------------------

    public function test_policy_registry_dot_notation(): void
    {
        $registry = new PolicyRegistry();
        $principal = new Principal(id: 1, roles: ['author']);

        $policy = new class extends ResourcePolicy {
            public function can(PrincipalInterface $principal, string $ability, mixed $subject = null): bool
            {
                return match ($ability) {
                    'view' => true,
                    'edit' => $principal->hasRole('author'),
                    default => false,
                };
            }
        };

        $registry->register('posts', $policy);

        $this->assertTrue($registry->can($principal, 'posts.view'));
        $this->assertTrue($registry->can($principal, 'posts.edit'));
        $this->assertFalse($registry->can($principal, 'posts.delete'));
    }

    public function test_policy_registry_returns_false_without_dot(): void
    {
        $registry = new PolicyRegistry();
        $principal = new Principal(id: 1);

        $this->assertFalse($registry->can($principal, 'nodot'));
    }

    public function test_policy_registry_returns_false_for_unregistered(): void
    {
        $registry = new PolicyRegistry();
        $principal = new Principal(id: 1);

        $this->assertFalse($registry->can($principal, 'unknown.view'));
    }

    // -----------------------------------------------------------------------
    // Policy attribute tests
    // -----------------------------------------------------------------------

    public function test_policy_attribute_stores_model_class(): void
    {
        $attr = new PolicyAttribute(model: User::class);
        $this->assertSame(User::class, $attr->model);
    }

    public function test_can_attribute_stores_ability(): void
    {
        $attr = new Can(ability: 'posts.edit');
        $this->assertSame('posts.edit', $attr->ability);
    }

    public function test_policy_attribute_on_class(): void
    {
        $ref = new ReflectionClass(StubContactPolicy::class);
        $attrs = $ref->getAttributes(PolicyAttribute::class);

        $this->assertCount(1, $attrs);
        $instance = $attrs[0]->newInstance();
        $this->assertSame('StubContact', $instance->model);
    }

    // -----------------------------------------------------------------------
    // Principal role/scope checks (unit-level, in-memory)
    // -----------------------------------------------------------------------

    public function test_principal_has_role(): void
    {
        $principal = new Principal(id: 1, roles: ['admin', 'editor']);

        $this->assertTrue($principal->hasRole('admin'));
        $this->assertTrue($principal->hasRole('editor'));
        $this->assertFalse($principal->hasRole('viewer'));
    }

    public function test_principal_has_scope(): void
    {
        $principal = new Principal(id: 1, scopes: ['read', 'write']);

        $this->assertTrue($principal->hasScope('read'));
        $this->assertFalse($principal->hasScope('admin'));
    }

    // -----------------------------------------------------------------------
    // Super admin bypass via Gate before()
    // -----------------------------------------------------------------------

    public function test_super_admin_bypasses_all_gate_checks(): void
    {
        $gate = new Gate();

        // Register super admin bypass
        $gate->before(function (PrincipalInterface $p): ?bool {
            if ($p->hasRole('super-admin')) {
                return true;
            }
            return null;
        });

        $gate->define('manage-users', fn (PrincipalInterface $p) => false); // normally denied
        $gate->define('delete-everything', fn (PrincipalInterface $p) => false);

        $superAdmin = new Principal(id: 1, roles: ['super-admin']);
        $regularUser = new Principal(id: 2, roles: ['user']);

        $this->assertTrue($gate->allows($superAdmin, 'manage-users'));
        $this->assertTrue($gate->allows($superAdmin, 'delete-everything'));
        $this->assertTrue($gate->allows($superAdmin, 'undefined-ability'));
        $this->assertFalse($gate->allows($regularUser, 'manage-users'));
    }

    // -----------------------------------------------------------------------
    // Model structure verification (no DB needed)
    // -----------------------------------------------------------------------

    public function test_role_model_fillable(): void
    {
        $role = new Role();
        $this->assertSame(['name', 'slug', 'description', 'guard_name'], $role->getFillable());
    }

    public function test_permission_model_fillable(): void
    {
        $permission = new Permission();
        $this->assertSame(['name', 'slug', 'description', 'guard_name'], $permission->getFillable());
    }

    public function test_user_model_uses_has_roles_trait(): void
    {
        $traits = $this->getAllTraits(User::class);
        $this->assertContains(HasRoles::class, $traits);
    }

    public function test_user_model_uses_has_permissions_trait(): void
    {
        $traits = $this->getAllTraits(User::class);
        $this->assertContains(HasPermissions::class, $traits);
    }

    /** @return list<class-string> */
    private function getAllTraits(string $class): array
    {
        $traits = [];
        foreach (class_uses($class) ?: [] as $trait) {
            $traits[] = $trait;
        }
        foreach (class_parents($class) ?: [] as $parent) {
            foreach (class_uses($parent) ?: [] as $trait) {
                $traits[] = $trait;
            }
        }
        return array_unique($traits);
    }

    public function test_role_model_has_permissions_relation(): void
    {
        $this->assertTrue(method_exists(Role::class, 'permissions'));
    }

    public function test_role_model_has_users_relation(): void
    {
        $this->assertTrue(method_exists(Role::class, 'users'));
    }

    public function test_permission_model_has_roles_relation(): void
    {
        $this->assertTrue(method_exists(Permission::class, 'roles'));
    }

    // -----------------------------------------------------------------------
    // Combined gate + policy authorization flow
    // -----------------------------------------------------------------------

    public function test_combined_gate_and_policy_flow(): void
    {
        $gate = new Gate();
        $registry = new PolicyRegistry();

        // Gate-level ability
        $gate->define('view-dashboard', fn (PrincipalInterface $p) => $p->hasRole('admin'));

        // Policy-level ability
        $postPolicy = new class extends ResourcePolicy {
            public function can(PrincipalInterface $principal, string $ability, mixed $subject = null): bool
            {
                return match ($ability) {
                    'view' => true,
                    'edit' => $principal->hasRole('author'),
                    default => false,
                };
            }
        };
        $registry->register('posts', $postPolicy);

        $admin = new Principal(id: 1, roles: ['admin']);
        $author = new Principal(id: 2, roles: ['author']);
        $viewer = new Principal(id: 3, roles: ['viewer']);

        // Gate checks
        $this->assertTrue($gate->allows($admin, 'view-dashboard'));
        $this->assertFalse($gate->allows($author, 'view-dashboard'));

        // Policy checks
        $this->assertTrue($registry->can($viewer, 'posts.view'));
        $this->assertTrue($registry->can($author, 'posts.edit'));
        $this->assertFalse($registry->can($viewer, 'posts.edit'));
    }

    // -----------------------------------------------------------------------
    // Migration file existence
    // -----------------------------------------------------------------------

    public function test_migration_files_exist(): void
    {
        $migrationDir = __DIR__ . '/../../src/Database/Migrations/';

        $this->assertFileExists($migrationDir . 'CreateRolesTable.php');
        $this->assertFileExists($migrationDir . 'CreatePermissionsTable.php');
        $this->assertFileExists($migrationDir . 'CreateRolePermissionsTable.php');
        $this->assertFileExists($migrationDir . 'CreateUserRolesTable.php');
        $this->assertFileExists($migrationDir . 'CreateUserPermissionsTable.php');
    }
}

// -----------------------------------------------------------------------
// Stubs for attribute tests
// -----------------------------------------------------------------------

#[PolicyAttribute(model: 'StubContact')]
final class StubContactPolicy extends ResourcePolicy
{
    public function can(PrincipalInterface $principal, string $ability, mixed $subject = null): bool
    {
        return $principal->hasRole('admin');
    }
}
