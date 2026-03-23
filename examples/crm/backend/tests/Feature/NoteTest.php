<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\Note;
use Tests\TestCase;

/**
 * End-to-end tests for the Notes module.
 *
 * Covers: CRUD operations, polymorphic entity endpoint, pin feature,
 * validation errors, unauthorized access, and workspace isolation.
 */
final class NoteTest extends TestCase
{
    private Contact $contact;
    private Company $company;
    private Deal $deal;

    protected function setUp(): void
    {
        parent::setUp();

        $this->contact = Contact::create([
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'jane.doe@test.com',
            'status' => 'lead',
            'workspace_id' => $this->workspace->id,
            'owner_id' => $this->user->id,
        ]);

        $this->company = Company::create([
            'name' => 'Acme Corp',
            'domain' => 'acme.com',
            'workspace_id' => $this->workspace->id,
            'owner_id' => $this->user->id,
        ]);

        $this->deal = Deal::create([
            'title' => 'Big Deal',
            'value' => 100000,
            'currency' => 'USD',
            'stage' => 'qualified',
            'probability' => 50,
            'contact_id' => $this->contact->id,
            'company_id' => $this->company->id,
            'workspace_id' => $this->workspace->id,
            'owner_id' => $this->user->id,
        ]);
    }

    // -------------------------------------------------------
    // CRUD: Create
    // -------------------------------------------------------

    public function test_create_note_for_contact(): void
    {
        $response = $this->postJson('/api/notes', [
            'body' => 'This is a note for a contact.',
            'notable_type' => 'contacts',
            'notable_id' => $this->contact->id,
        ]);

        $response->assertCreated();
        $response->assertJsonStructure([
            'data' => ['id', 'body', 'notable_type', 'notable_id', 'author_id', 'is_pinned', 'created_at', 'updated_at'],
        ]);
        $response->assertJsonPath('data.body', 'This is a note for a contact.');
        $response->assertJsonPath('data.notable_id', $this->contact->id);

        $this->assertDatabaseHas('notes', [
            'content' => 'This is a note for a contact.',
            'notable_id' => $this->contact->id,
        ]);
    }

    public function test_create_note_for_company(): void
    {
        $response = $this->postJson('/api/notes', [
            'body' => 'Company-level note.',
            'notable_type' => 'companies',
            'notable_id' => $this->company->id,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.body', 'Company-level note.');
        $response->assertJsonPath('data.notable_id', $this->company->id);

        $this->assertDatabaseHas('notes', [
            'content' => 'Company-level note.',
            'notable_id' => $this->company->id,
        ]);
    }

    public function test_create_note_for_deal(): void
    {
        $response = $this->postJson('/api/notes', [
            'body' => 'Deal-level note.',
            'notable_type' => 'deals',
            'notable_id' => $this->deal->id,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.body', 'Deal-level note.');
        $response->assertJsonPath('data.notable_id', $this->deal->id);

        $this->assertDatabaseHas('notes', [
            'content' => 'Deal-level note.',
            'notable_id' => $this->deal->id,
        ]);
    }

    // -------------------------------------------------------
    // CRUD: List
    // -------------------------------------------------------

    public function test_list_notes_returns_results(): void
    {
        $this->seedNotes(3);

        $response = $this->getJson('/api/notes');

        $response->assertOk();
        $response->assertJsonStructure([
            'data',
            'meta' => ['current_page', 'per_page', 'total'],
        ]);
        $data = $response->getBody()['data'] ?? [];
        $this->assertCount(3, $data);
    }

    public function test_list_notes_empty_when_none_exist(): void
    {
        $response = $this->getJson('/api/notes');

        $response->assertOk();
        $response->assertJsonCount(0, 'data');
    }

    // -------------------------------------------------------
    // CRUD: Show
    // -------------------------------------------------------

    public function test_show_note_returns_full_details(): void
    {
        $note = $this->seedNotes(1)[0];

        $response = $this->getJson("/api/notes/{$note->id}");

        $response->assertOk();
        $response->assertJsonPath('data.id', $note->id);
        $response->assertJsonPath('data.body', $note->content);
        $response->assertJsonPath('data.is_pinned', false);
    }

    public function test_show_nonexistent_note_returns_404(): void
    {
        $response = $this->getJson('/api/notes/999999');

        $response->assertNotFound();
    }

    // -------------------------------------------------------
    // CRUD: Update
    // -------------------------------------------------------

    public function test_update_note_changes_body(): void
    {
        $note = $this->seedNotes(1)[0];

        $response = $this->putJson("/api/notes/{$note->id}", [
            'body' => 'Updated note body.',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.body', 'Updated note body.');

        $this->assertDatabaseHas('notes', [
            'id' => $note->id,
            'content' => 'Updated note body.',
        ]);
    }

    // -------------------------------------------------------
    // CRUD: Delete
    // -------------------------------------------------------

    public function test_delete_note_returns_no_content(): void
    {
        $note = $this->seedNotes(1)[0];

        $response = $this->deleteJson("/api/notes/{$note->id}");

        $response->assertNoContent();

        // Soft deleted — should not appear in normal queries
        $this->assertDatabaseMissing('notes', [
            'id' => $note->id,
            'deleted_at' => null,
        ]);
    }

    public function test_delete_nonexistent_note_returns_404(): void
    {
        $response = $this->deleteJson('/api/notes/999999');

        $response->assertNotFound();
    }

    // -------------------------------------------------------
    // Polymorphic entity endpoint
    // -------------------------------------------------------

    public function test_notes_for_contact_entity(): void
    {
        // Create notes for the contact and the company
        Note::create([
            'content' => 'Contact note',
            'notable_type' => Contact::class,
            'notable_id' => $this->contact->id,
            'author_id' => $this->user->id,
            'workspace_id' => $this->workspace->id,
            'is_pinned' => false,
        ]);

        Note::create([
            'content' => 'Company note',
            'notable_type' => Company::class,
            'notable_id' => $this->company->id,
            'author_id' => $this->user->id,
            'workspace_id' => $this->workspace->id,
            'is_pinned' => false,
        ]);

        $response = $this->getJson("/api/notes/for/contacts/{$this->contact->id}");

        $response->assertOk();
        $data = $response->getBody()['data'] ?? [];
        $this->assertCount(1, $data);
        $this->assertSame('Contact note', $data[0]['body']);
    }

    public function test_notes_for_company_entity(): void
    {
        Note::create([
            'content' => 'Company specific note',
            'notable_type' => Company::class,
            'notable_id' => $this->company->id,
            'author_id' => $this->user->id,
            'workspace_id' => $this->workspace->id,
            'is_pinned' => false,
        ]);

        Note::create([
            'content' => 'Deal specific note',
            'notable_type' => Deal::class,
            'notable_id' => $this->deal->id,
            'author_id' => $this->user->id,
            'workspace_id' => $this->workspace->id,
            'is_pinned' => false,
        ]);

        $response = $this->getJson("/api/notes/for/companies/{$this->company->id}");

        $response->assertOk();
        $data = $response->getBody()['data'] ?? [];
        $this->assertCount(1, $data);
        $this->assertSame('Company specific note', $data[0]['body']);
    }

    // -------------------------------------------------------
    // Pin feature
    // -------------------------------------------------------

    public function test_create_pinned_note(): void
    {
        $response = $this->postJson('/api/notes', [
            'body' => 'Important pinned note.',
            'notable_type' => 'contacts',
            'notable_id' => $this->contact->id,
            'is_pinned' => true,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.is_pinned', true);

        $this->assertDatabaseHas('notes', [
            'content' => 'Important pinned note.',
            'is_pinned' => true,
        ]);
    }

    public function test_update_note_pin_status(): void
    {
        $note = $this->seedNotes(1)[0];

        // Pin the note
        $response = $this->putJson("/api/notes/{$note->id}", [
            'is_pinned' => true,
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.is_pinned', true);

        // Unpin the note
        $response = $this->putJson("/api/notes/{$note->id}", [
            'is_pinned' => false,
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.is_pinned', false);
    }

    // -------------------------------------------------------
    // Validation
    // -------------------------------------------------------

    public function test_create_note_requires_body(): void
    {
        $response = $this->postJson('/api/notes', [
            'notable_type' => 'contacts',
            'notable_id' => $this->contact->id,
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['body']);
    }

    public function test_create_note_requires_notable_type(): void
    {
        $response = $this->postJson('/api/notes', [
            'body' => 'A note without a type.',
            'notable_id' => $this->contact->id,
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['notable_type']);
    }

    public function test_create_note_returns_422_with_empty_body(): void
    {
        $response = $this->postJson('/api/notes', []);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['body', 'notable_type']);
    }

    // -------------------------------------------------------
    // Authorization
    // -------------------------------------------------------

    public function test_list_notes_returns_401_without_auth(): void
    {
        $response = $this->asGuest()->getJson('/api/notes');

        $response->assertUnauthorized();
    }

    public function test_create_note_returns_401_without_auth(): void
    {
        $response = $this->asGuest()->postJson('/api/notes', [
            'body' => 'Unauthorized note.',
            'notable_type' => 'contacts',
            'notable_id' => $this->contact->id,
        ]);

        $response->assertUnauthorized();
    }

    // -------------------------------------------------------
    // Workspace isolation
    // -------------------------------------------------------

    public function test_notes_are_isolated_between_workspaces(): void
    {
        // Create note in workspace A (default from setUp)
        Note::create([
            'content' => 'Workspace A note',
            'notable_type' => Contact::class,
            'notable_id' => $this->contact->id,
            'author_id' => $this->user->id,
            'workspace_id' => $this->workspace->id,
            'is_pinned' => false,
        ]);

        // Verify workspace A sees 1 note
        $responseA = $this->getJson('/api/notes');
        $responseA->assertOk();
        $dataA = $responseA->getBody()['data'] ?? [];
        $this->assertCount(1, $dataA);
        $this->assertSame('Workspace A note', $dataA[0]['body']);

        // Switch to workspace B
        $userB = $this->createUser(['email' => 'note-userb@test.com', 'name' => 'Note User B']);
        $workspaceB = $this->createTestWorkspace($userB);
        $this->actingAsUser($userB, $workspaceB);

        // Workspace B should see 0 notes
        $responseB = $this->getJson('/api/notes');
        $responseB->assertOk();
        $dataB = $responseB->getBody()['data'] ?? [];
        $this->assertCount(0, $dataB);
    }

    // -------------------------------------------------------
    // Helpers
    // -------------------------------------------------------

    /**
     * Seed N notes attached to the default contact in the current workspace.
     *
     * @return list<Note>
     */
    private function seedNotes(int $count): array
    {
        $notes = [];

        for ($i = 0; $i < $count; $i++) {
            $notes[] = Note::create([
                'content' => "Test note {$i}",
                'notable_type' => Contact::class,
                'notable_id' => $this->contact->id,
                'author_id' => $this->user->id,
                'workspace_id' => $this->workspace->id,
                'is_pinned' => false,
            ]);
        }

        return $notes;
    }
}
