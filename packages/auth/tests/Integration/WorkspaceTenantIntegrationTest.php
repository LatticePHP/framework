<?php

declare(strict_types=1);

namespace Lattice\Auth\Tests\Integration;

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Events\Dispatcher;
use Lattice\Auth\Models\Tenant;
use Lattice\Auth\Models\User;
use Lattice\Auth\Models\Workspace;
use Lattice\Auth\Principal;
use Lattice\Auth\Tenancy\TenantContext;
use Lattice\Auth\Tenancy\TenantGuard;
use Lattice\Auth\Tenancy\TenantResolver;
use Lattice\Auth\Workspace\WorkspaceContext;
use Lattice\Auth\Workspace\WorkspaceGuard;
use Lattice\Auth\Workspace\WorkspaceResolver;
use Lattice\Contracts\Context\ExecutionContextInterface;
use Lattice\Contracts\Context\ExecutionType;
use Lattice\Contracts\Context\PrincipalInterface;
use Lattice\Http\HttpExecutionContext;
use Lattice\Http\Request;
use PHPUnit\Framework\TestCase;

// ---------------------------------------------------------------------------
// Inline test models that use BelongsToWorkspace / BelongsToTenant traits
// ---------------------------------------------------------------------------

/**
 * Test resource scoped to workspace only.
 */
class WorkspaceScopedResource extends \Lattice\Database\Model
{
    use \Lattice\Database\Traits\BelongsToWorkspace;

    protected $table = 'workspace_resources';
    protected $fillable = ['name', 'workspace_id'];
}

/**
 * Test resource scoped to tenant only.
 */
class TenantScopedResource extends \Illuminate\Database\Eloquent\Model
{
    use \Lattice\Database\Traits\BelongsToTenant;

    protected $table = 'tenant_resources';
    protected $fillable = ['name', 'tenant_id'];
}

/**
 * Test resource scoped to BOTH workspace and tenant.
 */
class DualScopedResource extends \Lattice\Database\Model
{
    use \Lattice\Database\Traits\BelongsToWorkspace;
    use \Lattice\Database\Traits\BelongsToTenant;

    protected $table = 'dual_resources';
    protected $fillable = ['name', 'workspace_id', 'tenant_id'];
}

// ---------------------------------------------------------------------------
// Integration test class
// ---------------------------------------------------------------------------

final class WorkspaceTenantIntegrationTest extends TestCase
{
    private static bool $migrated = false;

    protected function setUp(): void
    {
        parent::setUp();

        if (!self::$migrated) {
            $this->setUpDatabase();
            self::$migrated = true;
        }

        // Truncate all tables in dependency-safe order
        Capsule::table('dual_resources')->truncate();
        Capsule::table('tenant_resources')->truncate();
        Capsule::table('workspace_resources')->truncate();
        Capsule::table('workspace_members')->truncate();
        Capsule::table('workspaces')->truncate();
        Capsule::table('tenants')->truncate();
        Capsule::table('users')->truncate();

        // Always start with clean static context
        WorkspaceContext::reset();
        TenantContext::reset();

        // Clear Eloquent booted state so global scopes re-register cleanly
        WorkspaceScopedResource::clearBootedModels();
        TenantScopedResource::clearBootedModels();
        DualScopedResource::clearBootedModels();
    }

    protected function tearDown(): void
    {
        WorkspaceContext::reset();
        TenantContext::reset();
        parent::tearDown();
    }

    // =========================================================================
    // Database bootstrap
    // =========================================================================

    private function setUpDatabase(): void
    {
        $capsule = new Capsule();
        $capsule->addConnection([
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);
        $capsule->setAsGlobal();
        $capsule->bootEloquent();

        // Ensure model events work by setting an event dispatcher
        Model::setEventDispatcher(new Dispatcher());

        $schema = Capsule::schema();

        $schema->create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('role')->default('user');
            $table->string('remember_token')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamps();
        });

        $schema->create('workspaces', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->unsignedBigInteger('owner_id');
            $table->json('settings')->nullable();
            $table->string('logo_url', 2048)->nullable();
            $table->timestamps();

            $table->foreign('owner_id')->references('id')->on('users')->onDelete('cascade');
        });

        $schema->create('workspace_members', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('workspace_id');
            $table->unsignedBigInteger('user_id');
            $table->string('role', 50)->default('member');
            $table->timestamp('joined_at')->nullable();
            $table->unsignedBigInteger('invited_by')->nullable();
            $table->timestamps();

            $table->foreign('workspace_id')->references('id')->on('workspaces')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->unique(['workspace_id', 'user_id']);
        });

        $schema->create('tenants', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('domain')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();
        });

        $schema->create('workspace_resources', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('workspace_id')->nullable();
            $table->timestamps();
        });

        $schema->create('tenant_resources', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->timestamps();
        });

        $schema->create('dual_resources', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('workspace_id')->nullable();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->timestamps();
        });
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function createUser(string $name = 'Test User', string $email = 'test@example.com'): User
    {
        return User::create([
            'name' => $name,
            'email' => $email,
            'password' => 'password123',
            'role' => 'user',
        ]);
    }

    private function createWorkspaceWithOwner(User $owner, string $name = 'Test Workspace'): Workspace
    {
        $workspace = Workspace::create([
            'name' => $name,
            'slug' => strtolower(str_replace(' ', '-', $name)) . '-' . uniqid(),
            'owner_id' => $owner->id,
            'settings' => ['timezone' => 'UTC'],
        ]);

        $workspace->addMember($owner, 'owner');

        return $workspace;
    }

    private function createTenant(string $name, string $slug): Tenant
    {
        return Tenant::create([
            'name' => $name,
            'slug' => $slug,
        ]);
    }

    private function createMockContext(
        Request $request,
        ?PrincipalInterface $principal = null,
    ): ExecutionContextInterface {
        return new HttpExecutionContext(
            request: $request,
            module: 'TestModule',
            controllerClass: 'TestController',
            methodName: 'index',
            principal: $principal,
        );
    }

    // =========================================================================
    // 1. WorkspaceContext set / get / reset
    // =========================================================================

    public function test_workspace_context_set_get_reset(): void
    {
        $owner = $this->createUser();
        $workspace = $this->createWorkspaceWithOwner($owner);

        // Initially null
        $this->assertNull(WorkspaceContext::get());

        // Set
        WorkspaceContext::set($workspace);
        $this->assertSame($workspace, WorkspaceContext::get());
        $this->assertSame($workspace->id, WorkspaceContext::id());
        $this->assertTrue(WorkspaceContext::isActive());

        // Reset
        WorkspaceContext::reset();
        $this->assertNull(WorkspaceContext::get());
        $this->assertNull(WorkspaceContext::id());
        $this->assertFalse(WorkspaceContext::isActive());
    }

    // =========================================================================
    // 2. TenantContext set / get / reset
    // =========================================================================

    public function test_tenant_context_set_get_reset(): void
    {
        $tenant = $this->createTenant('Acme', 'acme');

        // Initially null
        $this->assertNull(TenantContext::get());

        // Set
        TenantContext::set($tenant);
        $this->assertSame($tenant, TenantContext::get());
        $this->assertSame($tenant->id, TenantContext::id());

        // Reset
        TenantContext::reset();
        $this->assertNull(TenantContext::get());
        $this->assertNull(TenantContext::id());
    }

    // =========================================================================
    // 3. BelongsToWorkspace auto-scope
    // =========================================================================

    public function test_belongs_to_workspace_auto_scope_filters_by_workspace(): void
    {
        $owner = $this->createUser();
        $ws1 = $this->createWorkspaceWithOwner($owner, 'WS1');
        $ws2Owner = $this->createUser('Owner2', 'owner2@example.com');
        $ws2 = $this->createWorkspaceWithOwner($ws2Owner, 'WS2');

        // Create 3 items in workspace 1
        WorkspaceContext::set($ws1);
        WorkspaceScopedResource::create(['name' => 'Item A']);
        WorkspaceScopedResource::create(['name' => 'Item B']);
        WorkspaceScopedResource::create(['name' => 'Item C']);

        // Create 2 items in workspace 2
        WorkspaceContext::set($ws2);
        WorkspaceScopedResource::create(['name' => 'Item D']);
        WorkspaceScopedResource::create(['name' => 'Item E']);

        // Query from workspace 1 context -- should only get 3
        WorkspaceContext::set($ws1);
        $ws1Items = WorkspaceScopedResource::all();
        $this->assertCount(3, $ws1Items);

        // Query from workspace 2 context -- should only get 2
        WorkspaceContext::set($ws2);
        $ws2Items = WorkspaceScopedResource::all();
        $this->assertCount(2, $ws2Items);
    }

    // =========================================================================
    // 4. BelongsToWorkspace auto-sets workspace_id
    // =========================================================================

    public function test_belongs_to_workspace_auto_sets_workspace_id(): void
    {
        $owner = $this->createUser();
        $workspace = $this->createWorkspaceWithOwner($owner);

        WorkspaceContext::set($workspace);

        // Do NOT pass workspace_id explicitly
        $resource = WorkspaceScopedResource::create(['name' => 'Auto-scoped']);

        $this->assertSame($workspace->id, $resource->workspace_id);
    }

    // =========================================================================
    // 5. BelongsToTenant auto-scope
    // =========================================================================

    public function test_belongs_to_tenant_auto_scope_filters_by_tenant(): void
    {
        $t1 = $this->createTenant('Tenant A', 'tenant-a');
        $t2 = $this->createTenant('Tenant B', 'tenant-b');

        // Create 3 items under tenant 1
        TenantContext::set($t1);
        TenantScopedResource::create(['name' => 'T1-A']);
        TenantScopedResource::create(['name' => 'T1-B']);
        TenantScopedResource::create(['name' => 'T1-C']);

        // Create 2 items under tenant 2
        TenantContext::set($t2);
        TenantScopedResource::create(['name' => 'T2-A']);
        TenantScopedResource::create(['name' => 'T2-B']);

        // Query from tenant 1 -- should only see 3
        TenantContext::set($t1);
        $this->assertCount(3, TenantScopedResource::all());

        // Query from tenant 2 -- should only see 2
        TenantContext::set($t2);
        $this->assertCount(2, TenantScopedResource::all());
    }

    // =========================================================================
    // 6. BelongsToTenant auto-sets tenant_id
    // =========================================================================

    public function test_belongs_to_tenant_auto_sets_tenant_id(): void
    {
        $tenant = $this->createTenant('Auto Tenant', 'auto-tenant');
        TenantContext::set($tenant);

        $resource = TenantScopedResource::create(['name' => 'Auto-scoped']);

        $this->assertSame($tenant->id, $resource->tenant_id);
    }

    // =========================================================================
    // 7. Workspace + Tenant combined (dual scope)
    // =========================================================================

    public function test_workspace_and_tenant_combined_filtering(): void
    {
        $owner1 = $this->createUser('Owner1', 'o1@example.com');
        $owner2 = $this->createUser('Owner2', 'o2@example.com');
        $ws1 = $this->createWorkspaceWithOwner($owner1, 'CombWS1');
        $ws2 = $this->createWorkspaceWithOwner($owner2, 'CombWS2');
        $t1 = $this->createTenant('CombT1', 'comb-t1');
        $t2 = $this->createTenant('CombT2', 'comb-t2');

        // WS1 + T1: 2 items
        WorkspaceContext::set($ws1);
        TenantContext::set($t1);
        DualScopedResource::create(['name' => 'WS1-T1-A']);
        DualScopedResource::create(['name' => 'WS1-T1-B']);

        // WS1 + T2: 1 item
        TenantContext::set($t2);
        DualScopedResource::create(['name' => 'WS1-T2-A']);

        // WS2 + T1: 1 item
        WorkspaceContext::set($ws2);
        TenantContext::set($t1);
        DualScopedResource::create(['name' => 'WS2-T1-A']);

        // Query WS1 + T1 -- should see 2
        WorkspaceContext::set($ws1);
        TenantContext::set($t1);
        $this->assertCount(2, DualScopedResource::all());

        // Query WS1 + T2 -- should see 1
        TenantContext::set($t2);
        $this->assertCount(1, DualScopedResource::all());

        // Query WS2 + T1 -- should see 1
        WorkspaceContext::set($ws2);
        TenantContext::set($t1);
        $this->assertCount(1, DualScopedResource::all());

        // Query WS2 + T2 -- should see 0
        TenantContext::set($t2);
        $this->assertCount(0, DualScopedResource::all());
    }

    // =========================================================================
    // 8. WorkspaceResolver from header (with real DB lookup)
    // =========================================================================

    public function test_workspace_resolver_from_header_with_database(): void
    {
        $owner = $this->createUser();
        $workspace = $this->createWorkspaceWithOwner($owner);

        $resolver = new WorkspaceResolver();
        $request = new Request(
            method: 'GET',
            uri: '/api/test',
            headers: ['X-Workspace-Id' => (string) $workspace->id],
        );

        $resolved = $resolver->resolve($request, 'header');

        $this->assertNotNull($resolved);
        $this->assertSame($workspace->id, $resolved->id);
        $this->assertSame($workspace->name, $resolved->name);
    }

    public function test_workspace_resolver_returns_null_for_nonexistent_workspace(): void
    {
        $resolver = new WorkspaceResolver();
        $request = new Request(
            method: 'GET',
            uri: '/api/test',
            headers: ['X-Workspace-Id' => '99999'],
        );

        $this->assertNull($resolver->resolve($request, 'header'));
    }

    // =========================================================================
    // 9. TenantResolver from header (with real DB lookup)
    // =========================================================================

    public function test_tenant_resolver_from_header_with_database(): void
    {
        $tenant = $this->createTenant('Resolve Tenant', 'resolve-tenant');

        $resolver = new TenantResolver();
        $request = new Request(
            method: 'GET',
            uri: '/api/test',
            headers: ['X-Tenant-Id' => (string) $tenant->id],
        );

        $resolved = $resolver->resolve($request, 'header');

        $this->assertNotNull($resolved);
        $this->assertSame($tenant->id, $resolved->id);
        $this->assertSame('Resolve Tenant', $resolved->name);
    }

    public function test_tenant_resolver_returns_null_for_nonexistent_tenant(): void
    {
        $resolver = new TenantResolver();
        $request = new Request(
            method: 'GET',
            uri: '/api/test',
            headers: ['X-Tenant-Id' => '99999'],
        );

        $this->assertNull($resolver->resolve($request, 'header'));
    }

    // =========================================================================
    // 10. WorkspaceGuard blocks non-member
    // =========================================================================

    public function test_workspace_guard_blocks_non_member(): void
    {
        $owner = $this->createUser('Owner', 'owner@example.com');
        $workspace = $this->createWorkspaceWithOwner($owner);

        $outsider = $this->createUser('Outsider', 'outsider@example.com');

        $resolver = new WorkspaceResolver();
        $guard = new WorkspaceGuard($resolver, 'header');

        $request = new Request(
            method: 'GET',
            uri: '/api/test',
            headers: ['X-Workspace-Id' => (string) $workspace->id],
        );

        $principal = new Principal(id: $outsider->id, type: 'user');
        $context = $this->createMockContext($request, $principal);

        $result = $guard->canActivate($context);

        $this->assertFalse($result);
        // WorkspaceContext should NOT be set when guard rejects
        $this->assertNull(WorkspaceContext::get());
    }

    public function test_workspace_guard_allows_member(): void
    {
        $owner = $this->createUser('Owner', 'owner@example.com');
        $workspace = $this->createWorkspaceWithOwner($owner);

        $resolver = new WorkspaceResolver();
        $guard = new WorkspaceGuard($resolver, 'header');

        $request = new Request(
            method: 'GET',
            uri: '/api/test',
            headers: ['X-Workspace-Id' => (string) $workspace->id],
        );

        $principal = new Principal(id: $owner->id, type: 'user');
        $context = $this->createMockContext($request, $principal);

        $result = $guard->canActivate($context);

        $this->assertTrue($result);
        $this->assertNotNull(WorkspaceContext::get());
        $this->assertSame($workspace->id, WorkspaceContext::id());
    }

    // =========================================================================
    // 11. Context reset between requests (simulated)
    // =========================================================================

    public function test_context_reset_between_requests(): void
    {
        $owner1 = $this->createUser('Owner1', 'owner1@example.com');
        $ws1 = $this->createWorkspaceWithOwner($owner1, 'RequestWS1');
        $t1 = $this->createTenant('ReqT1', 'req-t1');

        $owner2 = $this->createUser('Owner2', 'owner2@example.com');
        $ws2 = $this->createWorkspaceWithOwner($owner2, 'RequestWS2');
        $t2 = $this->createTenant('ReqT2', 'req-t2');

        // --- Simulate request 1 ---
        WorkspaceContext::set($ws1);
        TenantContext::set($t1);

        $this->assertSame($ws1->id, WorkspaceContext::id());
        $this->assertSame($t1->id, TenantContext::id());

        // End of request 1: reset
        WorkspaceContext::reset();
        TenantContext::reset();

        // --- Simulate request 2 ---
        WorkspaceContext::set($ws2);
        TenantContext::set($t2);

        // Verify request 2 sees its own workspace/tenant, not request 1's
        $this->assertSame($ws2->id, WorkspaceContext::id());
        $this->assertSame($t2->id, TenantContext::id());
        $this->assertNotSame($ws1->id, WorkspaceContext::id());
        $this->assertNotSame($t1->id, TenantContext::id());
    }

    // =========================================================================
    // Additional edge-case: TenantGuard integration with header strategy
    // =========================================================================

    public function test_tenant_guard_sets_context_on_success(): void
    {
        $tenant = $this->createTenant('Guard Tenant', 'guard-tenant');

        $resolver = new TenantResolver();
        $guard = new TenantGuard($resolver, 'header');

        $request = new Request(
            method: 'GET',
            uri: '/api/test',
            headers: ['X-Tenant-Id' => (string) $tenant->id],
        );

        $context = $this->createMockContext($request);

        $result = $guard->canActivate($context);

        $this->assertTrue($result);
        $this->assertNotNull(TenantContext::get());
        $this->assertSame($tenant->id, TenantContext::id());
    }

    public function test_tenant_guard_blocks_when_tenant_not_found(): void
    {
        $resolver = new TenantResolver();
        $guard = new TenantGuard($resolver, 'header');

        $request = new Request(
            method: 'GET',
            uri: '/api/test',
            headers: ['X-Tenant-Id' => '99999'],
        );

        $context = $this->createMockContext($request);

        $this->assertFalse($guard->canActivate($context));
        $this->assertNull(TenantContext::get());
    }
}
