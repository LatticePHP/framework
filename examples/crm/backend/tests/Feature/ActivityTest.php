<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Contact;
use Tests\TestCase;

/**
 * End-to-end tests for the Activities module.
 *
 * Covers: CRUD, upcoming/overdue/complete endpoints,
 * validation, authorization, workspace isolation, and full lifecycle.
 */
final class ActivityTest extends TestCase
{
    private Contact $contact;

    protected function setUp(): void
    {
        parent::setUp();

        $this->contact = Contact::create([
            'first_name' => 'Activity',
            'last_name' => 'Contact',
            'email' => 'activity-contact@test.com',
            'status' => 'lead',
            'workspace_id' => $this->workspace->id,
            'owner_id' => $this->user->id,
        ]);
    }

    // -------------------------------------------------------
    // CRUD: Create
    // -------------------------------------------------------

    public function test_create_activity_with_all_fields(): void
    {
        $dueDate = (new \DateTimeImmutable('+3 days'))->format('Y-m-d H:i:s');

        $response = $this->postJson('/api/activities', [
            'type' => 'call',
            'title' => 'Follow up with client',
            'description' => 'Discuss Q4 proposal details',
            'due_date' => $dueDate,
            'contact_id' => $this->contact->id,
        ]);

        $response->assertCreated();
        $response->assertJsonStructure([
            'data' => ['id', 'type', 'title', 'description', 'due_date', 'contact_id', 'owner_id'],
        ]);
        $response->assertJsonPath('data.type', 'call');
        $response->assertJsonPath('data.title', 'Follow up with client');
        $response->assertJsonPath('data.description', 'Discuss Q4 proposal details');
        $response->assertJsonPath('data.contact_id', $this->contact->id);

        $this->assertDatabaseHas('activities', [
            'type' => 'call',
            'subject' => 'Follow up with client',
            'description' => 'Discuss Q4 proposal details',
            'contact_id' => $this->contact->id,
        ]);
    }

    public function test_create_activity_with_minimal_fields(): void
    {
        $response = $this->postJson('/api/activities', [
            'type' => 'task',
            'title' => 'Quick task',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.type', 'task');
        $response->assertJsonPath('data.title', 'Quick task');
        $response->assertJsonPath('data.description', null);
        $response->assertJsonPath('data.due_date', null);
        $response->assertJsonPath('data.contact_id', null);

        $this->assertDatabaseHas('activities', [
            'type' => 'task',
            'subject' => 'Quick task',
        ]);
    }

    // -------------------------------------------------------
    // CRUD: List
    // -------------------------------------------------------

    public function test_list_activities_returns_paginated_results(): void
    {
        $this->seedActivities(5);

        $response = $this->getJson('/api/activities');

        $response->assertOk();
        $response->assertJsonStructure([
            'data',
            'meta' => ['current_page', 'per_page', 'total'],
        ]);
        $response->assertJsonCount(5, 'data');
    }

    public function test_list_activities_empty_when_none_exist(): void
    {
        $response = $this->getJson('/api/activities');

        $response->assertOk();
        $response->assertJsonCount(0, 'data');
    }

    // -------------------------------------------------------
    // CRUD: Show
    // -------------------------------------------------------

    public function test_show_activity_returns_full_details(): void
    {
        $activity = $this->seedActivity('meeting', 'Team standup');

        $response = $this->getJson("/api/activities/{$activity->id}");

        $response->assertOk();
        $response->assertJsonPath('data.id', $activity->id);
        $response->assertJsonPath('data.type', 'meeting');
        $response->assertJsonPath('data.title', 'Team standup');
    }

    public function test_show_nonexistent_activity_returns_404(): void
    {
        $response = $this->getJson('/api/activities/999999');

        $response->assertNotFound();
    }

    // -------------------------------------------------------
    // CRUD: Update
    // -------------------------------------------------------

    public function test_update_activity_changes_fields(): void
    {
        $activity = $this->seedActivity('call', 'Original title');

        $response = $this->putJson("/api/activities/{$activity->id}", [
            'title' => 'Updated title',
            'type' => 'email',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.title', 'Updated title');
        $response->assertJsonPath('data.type', 'email');

        $this->assertDatabaseHas('activities', [
            'id' => $activity->id,
            'subject' => 'Updated title',
            'type' => 'email',
        ]);
    }

    public function test_update_nonexistent_activity_returns_404(): void
    {
        $response = $this->putJson('/api/activities/999999', [
            'title' => 'Ghost',
        ]);

        $response->assertNotFound();
    }

    // -------------------------------------------------------
    // CRUD: Delete
    // -------------------------------------------------------

    public function test_delete_activity_returns_no_content(): void
    {
        $activity = $this->seedActivity('task', 'Delete me');

        $response = $this->deleteJson("/api/activities/{$activity->id}");

        $response->assertNoContent();

        // Soft deleted -- should not appear in normal queries
        $this->assertDatabaseMissing('activities', [
            'id' => $activity->id,
            'deleted_at' => null,
        ]);
    }

    public function test_delete_nonexistent_activity_returns_404(): void
    {
        $response = $this->deleteJson('/api/activities/999999');

        $response->assertNotFound();
    }

    // -------------------------------------------------------
    // Specialized endpoints
    // -------------------------------------------------------

    public function test_upcoming_activities_returns_future_due(): void
    {
        $futureDate = (new \DateTimeImmutable('+3 days'))->format('Y-m-d H:i:s');

        $activity = $this->seedActivity('call', 'Future call', $futureDate);

        $response = $this->getJson('/api/activities/upcoming');

        $response->assertOk();

        $data = $response->getBody()['data'] ?? [];
        $ids = array_column($data, 'id');
        $this->assertContains($activity->id, $ids);
    }

    public function test_overdue_activities_returns_past_due(): void
    {
        $pastDate = (new \DateTimeImmutable('-2 days'))->format('Y-m-d H:i:s');

        $activity = $this->seedActivity('task', 'Overdue task', $pastDate);

        $response = $this->getJson('/api/activities/overdue');

        $response->assertOk();

        $data = $response->getBody()['data'] ?? [];
        $ids = array_column($data, 'id');
        $this->assertContains($activity->id, $ids);
    }

    public function test_complete_activity_sets_completed_at(): void
    {
        $activity = $this->seedActivity('task', 'Complete me');

        $response = $this->postJson("/api/activities/{$activity->id}/complete");

        $response->assertOk();
        $response->assertJsonPath('data.is_completed', true);
        $this->assertNotNull($response->getBody()['data']['completed_at']);

        $fresh = Activity::withoutGlobalScopes()->find($activity->id);
        $this->assertNotNull($fresh->completed_at);
    }

    public function test_completed_activity_not_in_upcoming(): void
    {
        $futureDate = (new \DateTimeImmutable('+3 days'))->format('Y-m-d H:i:s');

        $activity = $this->seedActivity('call', 'Will be completed', $futureDate);

        // Complete it
        $this->postJson("/api/activities/{$activity->id}/complete")->assertOk();

        // Check upcoming -- should not include the completed activity
        $response = $this->getJson('/api/activities/upcoming');
        $response->assertOk();

        $data = $response->getBody()['data'] ?? [];
        $ids = array_column($data, 'id');
        $this->assertNotContains($activity->id, $ids);
    }

    // -------------------------------------------------------
    // Validation
    // -------------------------------------------------------

    public function test_create_activity_requires_type(): void
    {
        $response = $this->postJson('/api/activities', [
            'title' => 'Missing type',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['type']);
    }

    public function test_create_activity_requires_title(): void
    {
        $response = $this->postJson('/api/activities', [
            'type' => 'call',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['title']);
    }

    public function test_create_activity_validates_type_enum(): void
    {
        $response = $this->postJson('/api/activities', [
            'type' => 'invalid_type',
            'title' => 'Bad type',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['type']);
    }

    public function test_create_activity_returns_422_with_empty_body(): void
    {
        $response = $this->postJson('/api/activities', []);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['type', 'title']);
    }

    // -------------------------------------------------------
    // Authorization
    // -------------------------------------------------------

    public function test_list_activities_returns_401_without_auth(): void
    {
        $response = $this->asGuest()->getJson('/api/activities');

        $response->assertUnauthorized();
    }

    public function test_create_activity_returns_401_without_auth(): void
    {
        $response = $this->asGuest()->postJson('/api/activities', [
            'type' => 'call',
            'title' => 'Unauthorized call',
        ]);

        $response->assertUnauthorized();
    }

    public function test_complete_activity_returns_401_without_auth(): void
    {
        $activity = $this->seedActivity('task', 'Auth check');

        $response = $this->asGuest()->postJson("/api/activities/{$activity->id}/complete");

        $response->assertUnauthorized();
    }

    // -------------------------------------------------------
    // Workspace isolation
    // -------------------------------------------------------

    public function test_activities_are_isolated_between_workspaces(): void
    {
        // --- Workspace A (default from setUp) ---
        $this->seedActivity('call', 'Workspace A call');

        // Verify workspace A sees 1 activity
        $responseA = $this->getJson('/api/activities');
        $responseA->assertOk();
        $dataA = $responseA->getBody()['data'] ?? [];
        $this->assertCount(1, $dataA);
        $this->assertSame('Workspace A call', $dataA[0]['title']);

        // --- Workspace B ---
        $userB = $this->createUser(['email' => 'activity-userb@test.com', 'name' => 'User B']);
        $workspaceB = $this->createTestWorkspace($userB);
        $this->actingAsUser($userB, $workspaceB);

        // Workspace B should see 0 activities
        $responseB = $this->getJson('/api/activities');
        $responseB->assertOk();
        $dataB = $responseB->getBody()['data'] ?? [];
        $this->assertCount(0, $dataB);
    }

    // -------------------------------------------------------
    // Full lifecycle
    // -------------------------------------------------------

    public function test_full_lifecycle_create_list_complete_delete(): void
    {
        // 1. Create
        $createResponse = $this->postJson('/api/activities', [
            'type' => 'meeting',
            'title' => 'Lifecycle meeting',
            'description' => 'End-to-end test',
            'due_date' => (new \DateTimeImmutable('+1 day'))->format('Y-m-d H:i:s'),
            'contact_id' => $this->contact->id,
        ]);
        $createResponse->assertCreated();
        $activityId = $createResponse->getBody()['data']['id'];

        // 2. List -- should contain 1 activity
        $listResponse = $this->getJson('/api/activities');
        $listResponse->assertOk();
        $listResponse->assertJsonCount(1, 'data');

        // 3. Show
        $showResponse = $this->getJson("/api/activities/{$activityId}");
        $showResponse->assertOk();
        $showResponse->assertJsonPath('data.title', 'Lifecycle meeting');
        $showResponse->assertJsonPath('data.is_completed', false);

        // 4. Complete
        $completeResponse = $this->postJson("/api/activities/{$activityId}/complete");
        $completeResponse->assertOk();
        $completeResponse->assertJsonPath('data.is_completed', true);

        // 5. Delete
        $deleteResponse = $this->deleteJson("/api/activities/{$activityId}");
        $deleteResponse->assertNoContent();

        // 6. Verify deleted -- show should 404
        $showAfterDelete = $this->getJson("/api/activities/{$activityId}");
        $showAfterDelete->assertNotFound();
    }

    // -------------------------------------------------------
    // Helpers
    // -------------------------------------------------------

    /**
     * Create a single activity in the current workspace.
     */
    private function seedActivity(
        string $type = 'task',
        string $subject = 'Test activity',
        ?string $dueDate = null,
    ): Activity {
        return Activity::create([
            'type' => $type,
            'subject' => $subject,
            'due_date' => $dueDate,
            'contact_id' => $this->contact->id,
            'workspace_id' => $this->workspace->id,
            'owner_id' => $this->user->id,
        ]);
    }

    /**
     * Seed N activities in the current workspace.
     *
     * @return list<Activity>
     */
    private function seedActivities(int $count): array
    {
        $activities = [];

        for ($i = 0; $i < $count; $i++) {
            $activities[] = $this->seedActivity('task', "Activity {$i}");
        }

        return $activities;
    }
}
