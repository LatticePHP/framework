<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\Deal;
use App\Models\User;
use Lattice\Auth\Models\Workspace;
use Tests\TestCase;

/**
 * End-to-end tests for workspace isolation and invitation flow.
 *
 * Covers: data isolation between workspaces (contacts and deals created
 * in workspace A are not visible from workspace B), and the workspace
 * invitation/membership flow.
 */
final class WorkspaceTest extends TestCase
{
    // -------------------------------------------------------
    // Workspace isolation
    // -------------------------------------------------------

    public function test_contacts_are_isolated_between_workspaces(): void
    {
        // --- Workspace A (default from setUp) ---
        Contact::create([
            'first_name' => 'Alice',
            'last_name' => 'InWorkspaceA',
            'email' => 'alice-a@test.com',
            'status' => 'lead',
            'workspace_id' => $this->workspace->id,
            'owner_id' => $this->user->id,
        ]);

        // Verify workspace A sees 1 contact
        $responseA = $this->getJson('/api/contacts');
        $responseA->assertOk();
        $dataA = $responseA->getBody()['data'] ?? [];
        $this->assertCount(1, $dataA);
        $this->assertSame('alice-a@test.com', $dataA[0]['email']);

        // --- Workspace B ---
        $userB = $this->createUser(['email' => 'userb@test.com', 'name' => 'User B']);
        $workspaceB = $this->createTestWorkspace($userB);

        Contact::create([
            'first_name' => 'Bob',
            'last_name' => 'InWorkspaceB',
            'email' => 'bob-b@test.com',
            'status' => 'customer',
            'workspace_id' => $workspaceB->id,
            'owner_id' => $userB->id,
        ]);

        // Switch to workspace B context
        $this->actingAsUser($userB, $workspaceB);

        $responseB = $this->getJson('/api/contacts');
        $responseB->assertOk();
        $dataB = $responseB->getBody()['data'] ?? [];

        // Workspace B should only see its own contact
        $this->assertCount(1, $dataB);
        $this->assertSame('bob-b@test.com', $dataB[0]['email']);

        // Alice from workspace A should NOT appear
        $emails = array_column($dataB, 'email');
        $this->assertNotContains('alice-a@test.com', $emails);
    }

    public function test_deals_are_isolated_between_workspaces(): void
    {
        $contactA = Contact::create([
            'first_name' => 'DealContact',
            'last_name' => 'A',
            'email' => 'dealcontact-a@test.com',
            'status' => 'lead',
            'workspace_id' => $this->workspace->id,
            'owner_id' => $this->user->id,
        ]);

        // Create deal in workspace A
        Deal::create([
            'title' => 'Deal in Workspace A',
            'value' => 50000,
            'currency' => 'USD',
            'stage' => 'lead',
            'probability' => 10,
            'contact_id' => $contactA->id,
            'workspace_id' => $this->workspace->id,
            'owner_id' => $this->user->id,
        ]);

        // Verify workspace A sees 1 deal
        $responseA = $this->getJson('/api/deals');
        $responseA->assertOk();
        $dataA = $responseA->getBody()['data'] ?? [];
        $this->assertCount(1, $dataA);

        // Switch to workspace B
        $userB = $this->createUser(['email' => 'deal-userb@test.com', 'name' => 'Deal User B']);
        $workspaceB = $this->createTestWorkspace($userB);
        $this->actingAsUser($userB, $workspaceB);

        // Workspace B should see 0 deals
        $responseB = $this->getJson('/api/deals');
        $responseB->assertOk();
        $dataB = $responseB->getBody()['data'] ?? [];
        $this->assertCount(0, $dataB);
    }

    public function test_create_contact_in_workspace_a_not_visible_via_show_in_workspace_b(): void
    {
        // Create in workspace A
        $contact = Contact::create([
            'first_name' => 'Hidden',
            'last_name' => 'Contact',
            'email' => 'hidden@test.com',
            'status' => 'lead',
            'workspace_id' => $this->workspace->id,
            'owner_id' => $this->user->id,
        ]);

        // Switch to workspace B
        $userB = $this->createUser(['email' => 'show-b@test.com', 'name' => 'Show User B']);
        $workspaceB = $this->createTestWorkspace($userB);
        $this->actingAsUser($userB, $workspaceB);

        // Attempting to show contact from workspace A should 404
        $response = $this->getJson("/api/contacts/{$contact->id}");

        $response->assertNotFound();
    }

    public function test_dashboard_stats_are_scoped_to_workspace(): void
    {
        // Seed data in workspace A
        Contact::create([
            'first_name' => 'WsA',
            'last_name' => 'Contact',
            'email' => 'ws-a@test.com',
            'status' => 'lead',
            'workspace_id' => $this->workspace->id,
            'owner_id' => $this->user->id,
        ]);

        // Get stats from workspace A
        $statsA = $this->getJson('/api/dashboard/stats');
        $statsA->assertOk();
        $totalA = $statsA->getBody()['data']['contacts']['total'] ?? 0;
        $this->assertGreaterThanOrEqual(1, $totalA);

        // Switch to workspace B with no data
        $userB = $this->createUser(['email' => 'stats-b@test.com']);
        $workspaceB = $this->createTestWorkspace($userB);
        $this->actingAsUser($userB, $workspaceB);

        $statsB = $this->getJson('/api/dashboard/stats');
        $statsB->assertOk();
        $totalB = $statsB->getBody()['data']['contacts']['total'] ?? 0;
        $this->assertSame(0, $totalB, 'Workspace B should have 0 contacts');
    }

    // -------------------------------------------------------
    // Invitation flow
    // -------------------------------------------------------

    public function test_invite_user_to_workspace(): void
    {
        $invitee = $this->createUser([
            'email' => 'invitee@test.com',
            'name' => 'Invitee',
        ]);

        $response = $this->postJson('/api/workspaces/' . $this->workspace->id . '/invitations', [
            'email' => 'invitee@test.com',
            'role' => 'member',
        ]);

        $response->assertSuccessful();

        // Verify invitation was recorded
        $this->assertDatabaseHas('workspace_members', [
            'workspace_id' => $this->workspace->id,
            'user_id' => $invitee->id,
            'role' => 'member',
        ]);
    }

    public function test_invited_member_can_access_workspace_resources(): void
    {
        // Create a contact in the workspace
        Contact::create([
            'first_name' => 'Shared',
            'last_name' => 'Contact',
            'email' => 'shared@test.com',
            'status' => 'lead',
            'workspace_id' => $this->workspace->id,
            'owner_id' => $this->user->id,
        ]);

        // Create a new user and add them as a member
        $member = $this->createUser(['email' => 'member@test.com', 'name' => 'Member']);
        $this->workspace->members()->attach($member->id, [
            'role' => 'member',
            'joined_at' => now(),
            'invited_by' => $this->user->id,
        ]);

        // Act as the member in the same workspace
        $this->actingAsUser($member, $this->workspace);

        // Member should see the shared contact
        $response = $this->getJson('/api/contacts');
        $response->assertOk();
        $data = $response->getBody()['data'] ?? [];
        $this->assertNotEmpty($data);
        $this->assertSame('shared@test.com', $data[0]['email']);
    }

    public function test_non_member_cannot_access_workspace_resources(): void
    {
        // Create contact in workspace A
        Contact::create([
            'first_name' => 'Private',
            'last_name' => 'Contact',
            'email' => 'private@test.com',
            'status' => 'lead',
            'workspace_id' => $this->workspace->id,
            'owner_id' => $this->user->id,
        ]);

        // Create user who is NOT a member of workspace A
        $outsider = $this->createUser(['email' => 'outsider@test.com', 'name' => 'Outsider']);
        $outsiderWorkspace = $this->createTestWorkspace($outsider);

        // Try to access workspace A's resources from outsider context
        // but setting workspace A header (should fail workspace guard)
        $this->actingAs($outsider);
        $this->withHeader('X-Workspace-Id', (string) $this->workspace->id);

        $response = $this->getJson('/api/contacts');

        $response->assertForbidden();
    }

    // -------------------------------------------------------
    // Workspace CRUD
    // -------------------------------------------------------

    public function test_create_new_workspace(): void
    {
        $response = $this->postJson('/api/workspaces', [
            'name' => 'New Business Workspace',
            'slug' => 'new-business',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.name', 'New Business Workspace');
        $response->assertJsonPath('data.slug', 'new-business');

        $this->assertDatabaseHas('workspaces', [
            'name' => 'New Business Workspace',
            'slug' => 'new-business',
            'owner_id' => $this->user->id,
        ]);
    }

    public function test_list_workspaces_returns_only_user_workspaces(): void
    {
        // Current user has 1 workspace from setUp
        $response = $this->getJson('/api/workspaces');

        $response->assertOk();
        $data = $response->getBody()['data'] ?? [];
        $this->assertCount(1, $data);
    }

    public function test_workspace_slug_must_be_unique(): void
    {
        $response = $this->postJson('/api/workspaces', [
            'name' => 'Duplicate Slug',
            'slug' => $this->workspace->slug, // same slug as existing
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['slug']);
    }
}
