<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Contact;
use Tests\TestCase;

/**
 * End-to-end tests for the Contact CRUD, search, filter, and pagination.
 *
 * Covers: create, list, show, update, delete, search by query,
 * filter by status, pagination, validation errors, and unauthorized access.
 */
final class ContactTest extends TestCase
{
    // -------------------------------------------------------
    // CRUD: Create
    // -------------------------------------------------------

    public function test_create_contact_with_all_fields(): void
    {
        $company = Company::create([
            'name' => 'Acme Corp',
            'domain' => 'acme.com',
            'workspace_id' => $this->workspace->id,
            'owner_id' => $this->user->id,
        ]);

        $response = $this->postJson('/api/contacts', [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@acme.com',
            'phone' => '+1-555-0100',
            'company_id' => $company->id,
            'title' => 'CTO',
            'status' => 'lead',
            'source' => 'web',
            'tags' => ['vip', 'enterprise'],
        ]);

        $response->assertCreated();
        $response->assertJsonStructure([
            'data' => ['id', 'first_name', 'last_name', 'email', 'status'],
        ]);
        $response->assertJsonPath('data.first_name', 'John');
        $response->assertJsonPath('data.last_name', 'Doe');
        $response->assertJsonPath('data.email', 'john.doe@acme.com');

        $this->assertDatabaseHas('contacts', [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@acme.com',
            'status' => 'lead',
        ]);
    }

    public function test_create_contact_with_minimal_fields(): void
    {
        $response = $this->postJson('/api/contacts', [
            'first_name' => 'Minimal',
            'last_name' => 'Contact',
            'email' => 'minimal@example.com',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.first_name', 'Minimal');
        $response->assertJsonPath('data.status', 'lead'); // default

        $this->assertDatabaseHas('contacts', [
            'email' => 'minimal@example.com',
            'status' => 'lead',
        ]);
    }

    // -------------------------------------------------------
    // CRUD: List
    // -------------------------------------------------------

    public function test_list_contacts_returns_paginated_results(): void
    {
        $this->seedContacts(5);

        $response = $this->getJson('/api/contacts');

        $response->assertOk();
        $response->assertJsonStructure([
            'data',
            'meta' => ['current_page', 'per_page', 'total'],
        ]);
    }

    public function test_list_contacts_empty_when_none_exist(): void
    {
        $response = $this->getJson('/api/contacts');

        $response->assertOk();
        $response->assertJsonCount(0, 'data');
    }

    // -------------------------------------------------------
    // CRUD: Show
    // -------------------------------------------------------

    public function test_show_contact_returns_full_details(): void
    {
        $contact = $this->seedContacts(1)[0];

        $response = $this->getJson("/api/contacts/{$contact->id}");

        $response->assertOk();
        $response->assertJsonPath('data.id', $contact->id);
        $response->assertJsonPath('data.first_name', $contact->first_name);
        $response->assertJsonPath('data.email', $contact->email);
    }

    public function test_show_nonexistent_contact_returns_404(): void
    {
        $response = $this->getJson('/api/contacts/999999');

        $response->assertNotFound();
    }

    // -------------------------------------------------------
    // CRUD: Update
    // -------------------------------------------------------

    public function test_update_contact_changes_fields(): void
    {
        $contact = $this->seedContacts(1)[0];

        $response = $this->putJson("/api/contacts/{$contact->id}", [
            'first_name' => 'Updated',
            'last_name' => 'Name',
            'status' => 'customer',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.first_name', 'Updated');
        $response->assertJsonPath('data.last_name', 'Name');
        $response->assertJsonPath('data.status', 'customer');

        $this->assertDatabaseHas('contacts', [
            'id' => $contact->id,
            'first_name' => 'Updated',
            'status' => 'customer',
        ]);
    }

    public function test_update_nonexistent_contact_returns_404(): void
    {
        $response = $this->putJson('/api/contacts/999999', [
            'first_name' => 'Ghost',
        ]);

        $response->assertNotFound();
    }

    // -------------------------------------------------------
    // CRUD: Delete
    // -------------------------------------------------------

    public function test_delete_contact_returns_no_content(): void
    {
        $contact = $this->seedContacts(1)[0];

        $response = $this->deleteJson("/api/contacts/{$contact->id}");

        $response->assertNoContent();

        // Soft deleted — should not appear in normal queries
        $this->assertDatabaseMissing('contacts', [
            'id' => $contact->id,
            'deleted_at' => null,
        ]);
    }

    public function test_delete_nonexistent_contact_returns_404(): void
    {
        $response = $this->deleteJson('/api/contacts/999999');

        $response->assertNotFound();
    }

    // -------------------------------------------------------
    // Full CRUD lifecycle
    // -------------------------------------------------------

    public function test_full_crud_lifecycle_create_list_show_update_delete(): void
    {
        // 1. Create
        $createResponse = $this->postJson('/api/contacts', [
            'first_name' => 'Lifecycle',
            'last_name' => 'Test',
            'email' => 'lifecycle@example.com',
            'status' => 'lead',
        ]);
        $createResponse->assertCreated();
        $contactId = $createResponse->getBody()['data']['id'];

        // 2. List — should contain 1 contact
        $listResponse = $this->getJson('/api/contacts');
        $listResponse->assertOk();
        $listResponse->assertJsonCount(1, 'data');

        // 3. Show
        $showResponse = $this->getJson("/api/contacts/{$contactId}");
        $showResponse->assertOk();
        $showResponse->assertJsonPath('data.first_name', 'Lifecycle');

        // 4. Update
        $updateResponse = $this->putJson("/api/contacts/{$contactId}", [
            'first_name' => 'Updated',
            'status' => 'customer',
        ]);
        $updateResponse->assertOk();
        $updateResponse->assertJsonPath('data.first_name', 'Updated');
        $updateResponse->assertJsonPath('data.status', 'customer');

        // 5. Delete
        $deleteResponse = $this->deleteJson("/api/contacts/{$contactId}");
        $deleteResponse->assertNoContent();

        // 6. Verify deleted — show should 404
        $showAfterDelete = $this->getJson("/api/contacts/{$contactId}");
        $showAfterDelete->assertNotFound();
    }

    // -------------------------------------------------------
    // Search
    // -------------------------------------------------------

    public function test_search_contacts_by_name(): void
    {
        Contact::create([
            'first_name' => 'John',
            'last_name' => 'Smith',
            'email' => 'john.smith@test.com',
            'status' => 'lead',
            'workspace_id' => $this->workspace->id,
            'owner_id' => $this->user->id,
        ]);

        Contact::create([
            'first_name' => 'Alice',
            'last_name' => 'Johnson',
            'email' => 'alice@test.com',
            'status' => 'lead',
            'workspace_id' => $this->workspace->id,
            'owner_id' => $this->user->id,
        ]);

        $response = $this->getJson('/api/contacts/search?q=john');

        $response->assertOk();

        // Should find "John Smith" and/or "Alice Johnson" depending on search impl
        // At minimum it should return results for partial match on "john"
        $data = $response->getBody()['data'] ?? [];
        $this->assertNotEmpty($data, 'Search for "john" should return at least one result');

        // Verify John Smith is in results
        $names = array_map(
            fn (array $c) => $c['first_name'] ?? '',
            $data,
        );
        $this->assertContains('John', $names);
    }

    public function test_search_requires_query_parameter(): void
    {
        $response = $this->getJson('/api/contacts/search');

        $response->assertUnprocessable();
    }

    // -------------------------------------------------------
    // Filter
    // -------------------------------------------------------

    public function test_filter_contacts_by_status(): void
    {
        Contact::create([
            'first_name' => 'Lead',
            'last_name' => 'Person',
            'email' => 'lead@test.com',
            'status' => 'lead',
            'workspace_id' => $this->workspace->id,
            'owner_id' => $this->user->id,
        ]);

        Contact::create([
            'first_name' => 'Customer',
            'last_name' => 'Person',
            'email' => 'customer@test.com',
            'status' => 'customer',
            'workspace_id' => $this->workspace->id,
            'owner_id' => $this->user->id,
        ]);

        $response = $this->getJson('/api/contacts?filter[status]=lead');

        $response->assertOk();

        $data = $response->getBody()['data'] ?? [];
        foreach ($data as $contact) {
            $this->assertSame('lead', $contact['status'], 'All returned contacts should have status=lead');
        }

        // Should contain lead, not customer
        $emails = array_column($data, 'email');
        $this->assertContains('lead@test.com', $emails);
        $this->assertNotContains('customer@test.com', $emails);
    }

    public function test_filter_contacts_by_source(): void
    {
        Contact::create([
            'first_name' => 'Web',
            'last_name' => 'User',
            'email' => 'web@test.com',
            'status' => 'lead',
            'source' => 'web',
            'workspace_id' => $this->workspace->id,
            'owner_id' => $this->user->id,
        ]);

        Contact::create([
            'first_name' => 'Referral',
            'last_name' => 'User',
            'email' => 'referral@test.com',
            'status' => 'lead',
            'source' => 'referral',
            'workspace_id' => $this->workspace->id,
            'owner_id' => $this->user->id,
        ]);

        $response = $this->getJson('/api/contacts?filter[source]=web');

        $response->assertOk();

        $data = $response->getBody()['data'] ?? [];
        $emails = array_column($data, 'email');
        $this->assertContains('web@test.com', $emails);
        $this->assertNotContains('referral@test.com', $emails);
    }

    // -------------------------------------------------------
    // Pagination
    // -------------------------------------------------------

    public function test_pagination_with_custom_page_and_per_page(): void
    {
        // Create 15 contacts
        $this->seedContacts(15);

        // Request page 2 with 10 per page — should get 5 results
        $response = $this->getJson('/api/contacts?page=2&per_page=10');

        $response->assertOk();
        $response->assertJsonPath('meta.current_page', 2);
        $response->assertJsonPath('meta.per_page', 10);
        $response->assertJsonCount(5, 'data');
    }

    public function test_pagination_first_page_returns_correct_count(): void
    {
        $this->seedContacts(25);

        $response = $this->getJson('/api/contacts?page=1&per_page=10');

        $response->assertOk();
        $response->assertJsonCount(10, 'data');
        $response->assertJsonPath('meta.total', 25);
    }

    public function test_pagination_beyond_last_page_returns_empty(): void
    {
        $this->seedContacts(5);

        $response = $this->getJson('/api/contacts?page=100&per_page=10');

        $response->assertOk();
        $response->assertJsonCount(0, 'data');
    }

    // -------------------------------------------------------
    // Validation
    // -------------------------------------------------------

    public function test_create_contact_requires_first_name(): void
    {
        $response = $this->postJson('/api/contacts', [
            'last_name' => 'Doe',
            'email' => 'missing-first@test.com',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['first_name']);
    }

    public function test_create_contact_requires_last_name(): void
    {
        $response = $this->postJson('/api/contacts', [
            'first_name' => 'John',
            'email' => 'missing-last@test.com',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['last_name']);
    }

    public function test_create_contact_requires_email(): void
    {
        $response = $this->postJson('/api/contacts', [
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['email']);
    }

    public function test_create_contact_validates_email_format(): void
    {
        $response = $this->postJson('/api/contacts', [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'not-an-email',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['email']);
    }

    public function test_create_contact_validates_status_enum(): void
    {
        $response = $this->postJson('/api/contacts', [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@test.com',
            'status' => 'invalid_status',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['status']);
    }

    public function test_create_contact_validates_source_enum(): void
    {
        $response = $this->postJson('/api/contacts', [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@test.com',
            'source' => 'invalid_source',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['source']);
    }

    public function test_create_contact_returns_422_with_empty_body(): void
    {
        $response = $this->postJson('/api/contacts', []);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['first_name', 'last_name', 'email']);
    }

    // -------------------------------------------------------
    // Authorization
    // -------------------------------------------------------

    public function test_list_contacts_returns_401_without_auth(): void
    {
        $response = $this->asGuest()->getJson('/api/contacts');

        $response->assertUnauthorized();
    }

    public function test_create_contact_returns_401_without_auth(): void
    {
        $response = $this->asGuest()->postJson('/api/contacts', [
            'first_name' => 'Unauth',
            'last_name' => 'User',
            'email' => 'unauth@test.com',
        ]);

        $response->assertUnauthorized();
    }

    public function test_show_contact_returns_401_without_auth(): void
    {
        $contact = $this->seedContacts(1)[0];

        $response = $this->asGuest()->getJson("/api/contacts/{$contact->id}");

        $response->assertUnauthorized();
    }

    public function test_update_contact_returns_401_without_auth(): void
    {
        $contact = $this->seedContacts(1)[0];

        $response = $this->asGuest()->putJson("/api/contacts/{$contact->id}", [
            'first_name' => 'Hacked',
        ]);

        $response->assertUnauthorized();
    }

    public function test_delete_contact_returns_401_without_auth(): void
    {
        $contact = $this->seedContacts(1)[0];

        $response = $this->asGuest()->deleteJson("/api/contacts/{$contact->id}");

        $response->assertUnauthorized();
    }

    // -------------------------------------------------------
    // Helpers
    // -------------------------------------------------------

    /**
     * Seed N contacts in the current workspace.
     *
     * @return list<Contact>
     */
    private function seedContacts(int $count): array
    {
        $contacts = [];

        for ($i = 0; $i < $count; $i++) {
            $contacts[] = Contact::create([
                'first_name' => "First{$i}",
                'last_name' => "Last{$i}",
                'email' => "contact{$i}-" . bin2hex(random_bytes(3)) . '@test.com',
                'status' => 'lead',
                'workspace_id' => $this->workspace->id,
                'owner_id' => $this->user->id,
            ]);
        }

        return $contacts;
    }
}
