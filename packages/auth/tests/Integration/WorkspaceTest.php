<?php

declare(strict_types=1);

namespace Lattice\Auth\Tests\Integration;

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use Lattice\Auth\Models\User;
use Lattice\Auth\Models\Workspace;
use Lattice\Auth\Models\WorkspaceInvitation;
use Lattice\Auth\Models\WorkspaceMember;
use Lattice\Auth\Principal;
use Lattice\Auth\Workspace\WorkspaceContext;
use Lattice\Auth\Workspace\WorkspaceGuard;
use Lattice\Auth\Workspace\WorkspaceResolver;
use Lattice\Contracts\Context\ExecutionContextInterface;
use Lattice\Contracts\Context\ExecutionType;
use Lattice\Contracts\Context\PrincipalInterface;
use Lattice\Http\Request;
use PHPUnit\Framework\TestCase;

final class WorkspaceTest extends TestCase
{
    private static bool $migrated = false;

    protected function setUp(): void
    {
        parent::setUp();

        if (!self::$migrated) {
            $this->setUpDatabase();
            self::$migrated = true;
        }

        // Clean tables before each test
        Capsule::table('workspace_invitations')->truncate();
        Capsule::table('workspace_members')->truncate();
        Capsule::table('workspaces')->truncate();
        Capsule::table('users')->truncate();

        WorkspaceContext::reset();
    }

    protected function tearDown(): void
    {
        WorkspaceContext::reset();
        parent::tearDown();
    }

    private function setUpDatabase(): void
    {
        $capsule = new Capsule();
        $capsule->addConnection([
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);
        $capsule->setAsGlobal();
        $capsule->bootEloquent();

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

        $schema->create('workspace_invitations', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('workspace_id');
            $table->string('email');
            $table->string('role', 50)->default('member');
            $table->string('token', 64)->unique();
            $table->unsignedBigInteger('invited_by')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->foreign('workspace_id')->references('id')->on('workspaces')->onDelete('cascade');
        });
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

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

    // -------------------------------------------------------------------------
    // Tests: Create workspace with owner
    // -------------------------------------------------------------------------

    public function test_create_workspace_with_owner(): void
    {
        $owner = $this->createUser();
        $workspace = $this->createWorkspaceWithOwner($owner);

        $this->assertNotNull($workspace->id);
        $this->assertSame('Test Workspace', $workspace->name);
        $this->assertSame($owner->id, $workspace->owner_id);
        $this->assertTrue($workspace->isOwner($owner));
        $this->assertTrue($workspace->hasMember($owner));
        $this->assertSame('owner', $workspace->getMemberRole($owner));
    }

    public function test_workspace_owner_relationship(): void
    {
        $owner = $this->createUser();
        $workspace = $this->createWorkspaceWithOwner($owner);

        $loadedOwner = $workspace->owner;
        $this->assertSame($owner->id, $loadedOwner->id);
    }

    public function test_workspace_settings_cast_to_array(): void
    {
        $owner = $this->createUser();
        $workspace = $this->createWorkspaceWithOwner($owner);

        $this->assertIsArray($workspace->settings);
        $this->assertSame('UTC', $workspace->settings['timezone']);
    }

    // -------------------------------------------------------------------------
    // Tests: Invite user -> accept invitation -> becomes member
    // -------------------------------------------------------------------------

    public function test_invite_user_and_accept_invitation(): void
    {
        $owner = $this->createUser('Owner', 'owner@example.com');
        $workspace = $this->createWorkspaceWithOwner($owner);

        // Create invitation
        $invitation = WorkspaceInvitation::create([
            'workspace_id' => $workspace->id,
            'email' => 'invitee@example.com',
            'role' => 'member',
            'token' => bin2hex(random_bytes(32)),
            'invited_by' => $owner->id,
            'expires_at' => now()->modify('+7 days'),
        ]);

        $this->assertTrue($invitation->isPending());
        $this->assertFalse($invitation->isAccepted());
        $this->assertFalse($invitation->isExpired());

        // Create the invited user and accept
        $invitee = $this->createUser('Invitee', 'invitee@example.com');
        $workspace->addMember($invitee, $invitation->role, $owner->id);
        $invitation->markAccepted();

        // Reload
        $invitation->refresh();

        $this->assertTrue($invitation->isAccepted());
        $this->assertFalse($invitation->isPending());
        $this->assertTrue($workspace->hasMember($invitee));
        $this->assertSame('member', $workspace->getMemberRole($invitee));
    }

    public function test_expired_invitation(): void
    {
        $owner = $this->createUser();
        $workspace = $this->createWorkspaceWithOwner($owner);

        $invitation = WorkspaceInvitation::create([
            'workspace_id' => $workspace->id,
            'email' => 'expired@example.com',
            'role' => 'member',
            'token' => bin2hex(random_bytes(32)),
            'invited_by' => $owner->id,
            'expires_at' => now()->modify('-1 day'),
        ]);

        $this->assertTrue($invitation->isExpired());
        $this->assertFalse($invitation->isPending());
    }

    public function test_invitation_belongs_to_workspace(): void
    {
        $owner = $this->createUser();
        $workspace = $this->createWorkspaceWithOwner($owner);

        $invitation = WorkspaceInvitation::create([
            'workspace_id' => $workspace->id,
            'email' => 'test@example.com',
            'role' => 'member',
            'token' => bin2hex(random_bytes(32)),
            'invited_by' => $owner->id,
            'expires_at' => now()->modify('+7 days'),
        ]);

        $this->assertSame($workspace->id, $invitation->workspace->id);
    }

    // -------------------------------------------------------------------------
    // Tests: WorkspaceContext set/get/reset
    // -------------------------------------------------------------------------

    public function test_workspace_context_set_and_get(): void
    {
        $owner = $this->createUser();
        $workspace = $this->createWorkspaceWithOwner($owner);

        $this->assertNull(WorkspaceContext::get());
        $this->assertNull(WorkspaceContext::id());
        $this->assertFalse(WorkspaceContext::isActive());

        WorkspaceContext::set($workspace);

        $this->assertSame($workspace->id, WorkspaceContext::id());
        $this->assertSame($workspace, WorkspaceContext::get());
        $this->assertTrue(WorkspaceContext::isActive());
    }

    public function test_workspace_context_reset(): void
    {
        $owner = $this->createUser();
        $workspace = $this->createWorkspaceWithOwner($owner);

        WorkspaceContext::set($workspace);
        $this->assertTrue(WorkspaceContext::isActive());

        WorkspaceContext::reset();
        $this->assertNull(WorkspaceContext::get());
        $this->assertNull(WorkspaceContext::id());
        $this->assertFalse(WorkspaceContext::isActive());
    }

    // -------------------------------------------------------------------------
    // Tests: WorkspaceGuard blocks non-member
    // -------------------------------------------------------------------------

    public function test_workspace_guard_blocks_when_no_workspace_header(): void
    {
        $resolver = new WorkspaceResolver();
        $guard = new WorkspaceGuard($resolver, 'header');

        $request = new Request('GET', '/api/test');
        $context = $this->createMockContext($request);

        $this->assertFalse($guard->canActivate($context));
    }

    public function test_workspace_guard_blocks_non_member(): void
    {
        $owner = $this->createUser('Owner', 'owner@example.com');
        $workspace = $this->createWorkspaceWithOwner($owner);

        $outsider = $this->createUser('Outsider', 'outsider@example.com');

        $resolver = new WorkspaceResolver();
        $guard = new WorkspaceGuard($resolver, 'header');

        $request = new Request('GET', '/api/test', [
            'X-Workspace-Id' => (string) $workspace->id,
        ]);

        $principal = new Principal(id: $outsider->id, type: 'user');
        $context = $this->createMockContext($request, $principal);

        $this->assertFalse($guard->canActivate($context));
        $this->assertNull(WorkspaceContext::get());
    }

    public function test_workspace_guard_allows_member(): void
    {
        $owner = $this->createUser('Owner', 'owner@example.com');
        $workspace = $this->createWorkspaceWithOwner($owner);

        $resolver = new WorkspaceResolver();
        $guard = new WorkspaceGuard($resolver, 'header');

        $request = new Request('GET', '/api/test', [
            'X-Workspace-Id' => (string) $workspace->id,
        ]);

        $principal = new Principal(id: $owner->id, type: 'user');
        $context = $this->createMockContext($request, $principal);

        $this->assertTrue($guard->canActivate($context));
        $this->assertNotNull(WorkspaceContext::get());
        $this->assertSame($workspace->id, WorkspaceContext::id());
    }

    // -------------------------------------------------------------------------
    // Tests: Workspace roles (owner can manage, member cannot)
    // -------------------------------------------------------------------------

    public function test_workspace_roles_owner_vs_member(): void
    {
        $owner = $this->createUser('Owner', 'owner@example.com');
        $workspace = $this->createWorkspaceWithOwner($owner);

        $member = $this->createUser('Member', 'member@example.com');
        $workspace->addMember($member, 'member');

        $this->assertSame('owner', $workspace->getMemberRole($owner));
        $this->assertSame('member', $workspace->getMemberRole($member));
        $this->assertTrue($workspace->isOwner($owner));
        $this->assertFalse($workspace->isOwner($member));
    }

    public function test_update_member_role(): void
    {
        $owner = $this->createUser('Owner', 'owner@example.com');
        $workspace = $this->createWorkspaceWithOwner($owner);

        $member = $this->createUser('Member', 'member@example.com');
        $workspace->addMember($member, 'member');

        $workspace->updateMemberRole($member, 'admin');

        $this->assertSame('admin', $workspace->getMemberRole($member));
    }

    public function test_remove_member(): void
    {
        $owner = $this->createUser('Owner', 'owner@example.com');
        $workspace = $this->createWorkspaceWithOwner($owner);

        $member = $this->createUser('Member', 'member@example.com');
        $workspace->addMember($member, 'member');

        $this->assertTrue($workspace->hasMember($member));

        $workspace->removeMember($member);

        $this->assertFalse($workspace->hasMember($member));
    }

    public function test_get_member_role_returns_null_for_non_member(): void
    {
        $owner = $this->createUser('Owner', 'owner@example.com');
        $workspace = $this->createWorkspaceWithOwner($owner);

        $nonMember = $this->createUser('NonMember', 'nonmember@example.com');

        $this->assertNull($workspace->getMemberRole($nonMember));
    }

    // -------------------------------------------------------------------------
    // Tests: User -> workspaces relationship
    // -------------------------------------------------------------------------

    public function test_user_workspaces_relationship(): void
    {
        $user = $this->createUser();
        $ws1 = $this->createWorkspaceWithOwner($user, 'Workspace 1');
        $ws2 = $this->createWorkspaceWithOwner($user, 'Workspace 2');

        $workspaces = $user->workspaces;
        $this->assertCount(2, $workspaces);
    }

    public function test_user_owned_workspaces(): void
    {
        $owner = $this->createUser('Owner', 'owner@example.com');
        $this->createWorkspaceWithOwner($owner, 'WS1');
        $this->createWorkspaceWithOwner($owner, 'WS2');

        $other = $this->createUser('Other', 'other@example.com');
        $this->createWorkspaceWithOwner($other, 'WS3');

        $this->assertCount(2, $owner->ownedWorkspaces);
    }

    // -------------------------------------------------------------------------
    // Tests: WorkspaceResolver strategies
    // -------------------------------------------------------------------------

    public function test_workspace_resolver_from_header(): void
    {
        $owner = $this->createUser();
        $workspace = $this->createWorkspaceWithOwner($owner);

        $resolver = new WorkspaceResolver();
        $request = new Request('GET', '/api/test', [
            'X-Workspace-Id' => (string) $workspace->id,
        ]);

        $resolved = $resolver->resolve($request, 'header');
        $this->assertNotNull($resolved);
        $this->assertSame($workspace->id, $resolved->id);
    }

    public function test_workspace_resolver_returns_null_for_missing_header(): void
    {
        $resolver = new WorkspaceResolver();
        $request = new Request('GET', '/api/test');

        $resolved = $resolver->resolve($request, 'header');
        $this->assertNull($resolved);
    }

    public function test_workspace_resolver_from_slug(): void
    {
        $owner = $this->createUser();
        $workspace = $this->createWorkspaceWithOwner($owner);

        $resolver = new WorkspaceResolver();
        $request = new Request('GET', '/api/test', [
            'X-Workspace-Slug' => $workspace->slug,
        ]);

        $resolved = $resolver->resolve($request, 'slug');
        $this->assertNotNull($resolved);
        $this->assertSame($workspace->id, $resolved->id);
    }

    public function test_workspace_resolver_from_url(): void
    {
        $owner = $this->createUser();
        $workspace = $this->createWorkspaceWithOwner($owner);

        $resolver = new WorkspaceResolver();
        $request = new Request('GET', "/api/workspaces/{$workspace->id}/projects");

        $resolved = $resolver->resolve($request, 'url');
        $this->assertNotNull($resolved);
        $this->assertSame($workspace->id, $resolved->id);
    }

    public function test_workspace_resolver_unknown_strategy_returns_null(): void
    {
        $resolver = new WorkspaceResolver();
        $request = new Request('GET', '/api/test');

        $resolved = $resolver->resolve($request, 'unknown');
        $this->assertNull($resolved);
    }

    // -------------------------------------------------------------------------
    // Tests: Workspace members list via relationship
    // -------------------------------------------------------------------------

    public function test_workspace_members_list(): void
    {
        $owner = $this->createUser('Owner', 'owner@example.com');
        $workspace = $this->createWorkspaceWithOwner($owner);

        $member1 = $this->createUser('Member1', 'member1@example.com');
        $member2 = $this->createUser('Member2', 'member2@example.com');

        $workspace->addMember($member1, 'admin', $owner->id);
        $workspace->addMember($member2, 'member', $owner->id);

        $members = $workspace->members;
        $this->assertCount(3, $members); // owner + 2 members
    }

    public function test_workspace_invitations_list(): void
    {
        $owner = $this->createUser();
        $workspace = $this->createWorkspaceWithOwner($owner);

        WorkspaceInvitation::create([
            'workspace_id' => $workspace->id,
            'email' => 'a@example.com',
            'role' => 'member',
            'token' => bin2hex(random_bytes(32)),
            'invited_by' => $owner->id,
            'expires_at' => now()->modify('+7 days'),
        ]);

        WorkspaceInvitation::create([
            'workspace_id' => $workspace->id,
            'email' => 'b@example.com',
            'role' => 'admin',
            'token' => bin2hex(random_bytes(32)),
            'invited_by' => $owner->id,
            'expires_at' => now()->modify('+7 days'),
        ]);

        $this->assertCount(2, $workspace->invitations);
    }

    // -------------------------------------------------------------------------
    // Tests: WorkspaceMember pivot
    // -------------------------------------------------------------------------

    public function test_workspace_member_pivot_has_role(): void
    {
        $owner = $this->createUser('Owner', 'owner@example.com');
        $workspace = $this->createWorkspaceWithOwner($owner);

        $member = $this->createUser('Member', 'member@example.com');
        $workspace->addMember($member, 'admin', $owner->id);

        $pivotMember = $workspace->members()->where('user_id', $member->id)->first();
        $this->assertNotNull($pivotMember);
        $this->assertSame('admin', $pivotMember->pivot->role);
        $this->assertSame($owner->id, $pivotMember->pivot->invited_by);
    }

    // -------------------------------------------------------------------------
    // Helpers for mock context
    // -------------------------------------------------------------------------

    private function createMockContext(Request $request, ?PrincipalInterface $principal = null): ExecutionContextInterface
    {
        return new class ($request, $principal) implements ExecutionContextInterface {
            public function __construct(
                private readonly Request $request,
                private readonly ?PrincipalInterface $principal,
            ) {}

            public function getType(): ExecutionType { return ExecutionType::Http; }
            public function getModule(): string { return 'test'; }
            public function getHandler(): string { return 'test'; }
            public function getClass(): string { return 'TestClass'; }
            public function getMethod(): string { return 'testMethod'; }
            public function getCorrelationId(): string { return 'test-correlation-id'; }
            public function getPrincipal(): ?PrincipalInterface { return $this->principal; }
            public function getRequest(): Request { return $this->request; }
        };
    }
}
