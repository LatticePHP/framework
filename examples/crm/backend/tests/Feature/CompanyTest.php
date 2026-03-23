<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Company;
use Tests\TestCase;

/**
 * End-to-end tests for the Company CRUD, search, validation,
 * authorization, and workspace isolation.
 */
final class CompanyTest extends TestCase
{
    // -------------------------------------------------------
    // CRUD: Create
    // -------------------------------------------------------

    public function test_create_company_with_all_fields(): void
    {
        $response = $this->postJson('/api/companies', [
            'name' => 'Acme Corporation',
            'domain' => 'acme.com',
            'industry' => 'technology',
            'size' => '51-200',
            'phone' => '+1-555-0100',
            'email' => 'info@acme.com',
            'address' => '123 Main St',
            'city' => 'San Francisco',
            'state' => 'CA',
            'country' => 'US',
            'website' => 'https://acme.com',
        ]);

        $response->assertCreated();
        $response->assertJsonStructure([
            'data' => ['id', 'name', 'domain', 'industry', 'size', 'email'],
        ]);
        $response->assertJsonPath('data.name', 'Acme Corporation');
        $response->assertJsonPath('data.domain', 'acme.com');
        $response->assertJsonPath('data.industry', 'technology');
        $response->assertJsonPath('data.size', '51-200');

        $this->assertDatabaseHas('companies', [
            'name' => 'Acme Corporation',
            'domain' => 'acme.com',
            'industry' => 'technology',
            'size' => '51-200',
            'email' => 'info@acme.com',
            'city' => 'San Francisco',
        ]);
    }

    public function test_create_company_with_minimal_fields(): void
    {
        $response = $this->postJson('/api/companies', [
            'name' => 'Minimal Corp',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.name', 'Minimal Corp');
        $response->assertJsonPath('data.domain', null);
        $response->assertJsonPath('data.industry', null);

        $this->assertDatabaseHas('companies', [
            'name' => 'Minimal Corp',
        ]);
    }

    // -------------------------------------------------------
    // CRUD: List
    // -------------------------------------------------------

    public function test_list_companies_returns_paginated_results(): void
    {
        $this->seedCompanies(5);

        $response = $this->getJson('/api/companies');

        $response->assertOk();
        $response->assertJsonStructure([
            'data',
            'meta' => ['current_page', 'per_page', 'total'],
        ]);
    }

    public function test_list_companies_empty_when_none_exist(): void
    {
        $response = $this->getJson('/api/companies');

        $response->assertOk();
        $response->assertJsonCount(0, 'data');
    }

    // -------------------------------------------------------
    // CRUD: Show
    // -------------------------------------------------------

    public function test_show_company_returns_full_details(): void
    {
        $company = $this->seedCompanies(1)[0];

        $response = $this->getJson("/api/companies/{$company->id}");

        $response->assertOk();
        $response->assertJsonPath('data.id', $company->id);
        $response->assertJsonPath('data.name', $company->name);
    }

    public function test_show_nonexistent_company_returns_404(): void
    {
        $response = $this->getJson('/api/companies/999999');

        $response->assertNotFound();
    }

    // -------------------------------------------------------
    // CRUD: Update
    // -------------------------------------------------------

    public function test_update_company_changes_fields(): void
    {
        $company = $this->seedCompanies(1)[0];

        $response = $this->putJson("/api/companies/{$company->id}", [
            'name' => 'Updated Corp',
            'industry' => 'finance',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.name', 'Updated Corp');
        $response->assertJsonPath('data.industry', 'finance');

        $this->assertDatabaseHas('companies', [
            'id' => $company->id,
            'name' => 'Updated Corp',
            'industry' => 'finance',
        ]);
    }

    public function test_update_nonexistent_company_returns_404(): void
    {
        $response = $this->putJson('/api/companies/999999', [
            'name' => 'Ghost',
        ]);

        $response->assertNotFound();
    }

    // -------------------------------------------------------
    // CRUD: Delete
    // -------------------------------------------------------

    public function test_delete_company_returns_no_content(): void
    {
        $company = $this->seedCompanies(1)[0];

        $response = $this->deleteJson("/api/companies/{$company->id}");

        $response->assertNoContent();

        // Soft deleted — should not appear in normal queries
        $this->assertDatabaseMissing('companies', [
            'id' => $company->id,
            'deleted_at' => null,
        ]);
    }

    public function test_delete_nonexistent_company_returns_404(): void
    {
        $response = $this->deleteJson('/api/companies/999999');

        $response->assertNotFound();
    }

    // -------------------------------------------------------
    // Search
    // -------------------------------------------------------

    public function test_search_companies_by_name(): void
    {
        Company::create([
            'name' => 'Acme Industries',
            'domain' => 'acme.com',
            'workspace_id' => $this->workspace->id,
            'owner_id' => $this->user->id,
        ]);

        Company::create([
            'name' => 'Globex Corporation',
            'domain' => 'globex.com',
            'workspace_id' => $this->workspace->id,
            'owner_id' => $this->user->id,
        ]);

        $response = $this->getJson('/api/companies/search?q=acme');

        $response->assertOk();

        $data = $response->getBody()['data'] ?? [];
        $this->assertNotEmpty($data, 'Search for "acme" should return at least one result');

        $names = array_map(
            fn (array $c) => $c['name'] ?? '',
            $data,
        );
        $this->assertContains('Acme Industries', $names);
    }

    public function test_search_requires_query_parameter(): void
    {
        $response = $this->getJson('/api/companies/search');

        $response->assertUnprocessable();
    }

    // -------------------------------------------------------
    // Full CRUD lifecycle
    // -------------------------------------------------------

    public function test_full_crud_lifecycle_create_list_show_update_delete(): void
    {
        // 1. Create
        $createResponse = $this->postJson('/api/companies', [
            'name' => 'Lifecycle Corp',
            'industry' => 'technology',
        ]);
        $createResponse->assertCreated();
        $companyId = $createResponse->getBody()['data']['id'];

        // 2. List — should contain 1 company
        $listResponse = $this->getJson('/api/companies');
        $listResponse->assertOk();
        $listResponse->assertJsonCount(1, 'data');

        // 3. Show
        $showResponse = $this->getJson("/api/companies/{$companyId}");
        $showResponse->assertOk();
        $showResponse->assertJsonPath('data.name', 'Lifecycle Corp');

        // 4. Update
        $updateResponse = $this->putJson("/api/companies/{$companyId}", [
            'name' => 'Updated Lifecycle Corp',
            'industry' => 'consulting',
        ]);
        $updateResponse->assertOk();
        $updateResponse->assertJsonPath('data.name', 'Updated Lifecycle Corp');
        $updateResponse->assertJsonPath('data.industry', 'consulting');

        // 5. Delete
        $deleteResponse = $this->deleteJson("/api/companies/{$companyId}");
        $deleteResponse->assertNoContent();

        // 6. Verify deleted — show should 404
        $showAfterDelete = $this->getJson("/api/companies/{$companyId}");
        $showAfterDelete->assertNotFound();
    }

    // -------------------------------------------------------
    // Validation
    // -------------------------------------------------------

    public function test_create_company_requires_name(): void
    {
        $response = $this->postJson('/api/companies', [
            'domain' => 'noname.com',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['name']);
    }

    public function test_create_company_validates_email_format(): void
    {
        $response = $this->postJson('/api/companies', [
            'name' => 'Bad Email Corp',
            'email' => 'not-an-email',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['email']);
    }

    public function test_create_company_returns_422_with_empty_body(): void
    {
        $response = $this->postJson('/api/companies', []);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['name']);
    }

    public function test_update_company_validates_industry_enum(): void
    {
        $company = $this->seedCompanies(1)[0];

        $response = $this->putJson("/api/companies/{$company->id}", [
            'industry' => 'invalid_industry',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['industry']);
    }

    public function test_update_company_validates_size_enum(): void
    {
        $company = $this->seedCompanies(1)[0];

        $response = $this->putJson("/api/companies/{$company->id}", [
            'size' => 'invalid_size',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['size']);
    }

    // -------------------------------------------------------
    // Authorization
    // -------------------------------------------------------

    public function test_list_companies_returns_401_without_auth(): void
    {
        $response = $this->asGuest()->getJson('/api/companies');

        $response->assertUnauthorized();
    }

    public function test_create_company_returns_401_without_auth(): void
    {
        $response = $this->asGuest()->postJson('/api/companies', [
            'name' => 'Unauth Corp',
        ]);

        $response->assertUnauthorized();
    }

    public function test_delete_company_returns_401_without_auth(): void
    {
        $company = $this->seedCompanies(1)[0];

        $response = $this->asGuest()->deleteJson("/api/companies/{$company->id}");

        $response->assertUnauthorized();
    }

    // -------------------------------------------------------
    // Workspace isolation
    // -------------------------------------------------------

    public function test_companies_are_isolated_between_workspaces(): void
    {
        // Create a company in the default workspace
        Company::create([
            'name' => 'Workspace A Company',
            'workspace_id' => $this->workspace->id,
            'owner_id' => $this->user->id,
        ]);

        // Create a second workspace with a different user
        $otherUser = $this->createUser();
        $otherWorkspace = $this->createTestWorkspace($otherUser);

        Company::create([
            'name' => 'Workspace B Company',
            'workspace_id' => $otherWorkspace->id,
            'owner_id' => $otherUser->id,
        ]);

        // Acting as default user in default workspace — should only see workspace A company
        $response = $this->getJson('/api/companies');
        $response->assertOk();

        $data = $response->getBody()['data'] ?? [];
        $names = array_column($data, 'name');
        $this->assertContains('Workspace A Company', $names);
        $this->assertNotContains('Workspace B Company', $names);

        // Switch to other user in other workspace — should only see workspace B company
        $this->actingAsUser($otherUser, $otherWorkspace);

        $response = $this->getJson('/api/companies');
        $response->assertOk();

        $data = $response->getBody()['data'] ?? [];
        $names = array_column($data, 'name');
        $this->assertContains('Workspace B Company', $names);
        $this->assertNotContains('Workspace A Company', $names);
    }

    // -------------------------------------------------------
    // Helpers
    // -------------------------------------------------------

    /**
     * Seed N companies in the current workspace.
     *
     * @return list<Company>
     */
    private function seedCompanies(int $count): array
    {
        $companies = [];

        for ($i = 0; $i < $count; $i++) {
            $companies[] = Company::create([
                'name' => "Company{$i}",
                'domain' => "company{$i}-" . bin2hex(random_bytes(3)) . '.com',
                'workspace_id' => $this->workspace->id,
                'owner_id' => $this->user->id,
            ]);
        }

        return $companies;
    }
}
