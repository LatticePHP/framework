<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Contact;
use App\Models\Deal;
use Tests\TestCase;

/**
 * End-to-end tests for deals and pipeline management.
 *
 * Covers: deal CRUD, pipeline view grouped by stage,
 * stage transitions, probability auto-update, and close-date handling.
 */
final class DealPipelineTest extends TestCase
{
    private Contact $contact;
    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'Pipeline Corp',
            'domain' => 'pipeline.com',
            'workspace_id' => $this->workspace->id,
            'owner_id' => $this->user->id,
        ]);

        $this->contact = Contact::create([
            'first_name' => 'Pipeline',
            'last_name' => 'Contact',
            'email' => 'pipeline@test.com',
            'status' => 'lead',
            'workspace_id' => $this->workspace->id,
            'owner_id' => $this->user->id,
        ]);
    }

    // -------------------------------------------------------
    // Create deal
    // -------------------------------------------------------

    public function test_create_deal_and_show_in_pipeline(): void
    {
        $createResponse = $this->postJson('/api/deals', [
            'title' => 'Big Enterprise Deal',
            'value' => 50000.00,
            'currency' => 'USD',
            'stage' => 'lead',
            'contact_id' => $this->contact->id,
            'company_id' => $this->company->id,
        ]);

        $createResponse->assertCreated();
        $createResponse->assertJsonPath('data.title', 'Big Enterprise Deal');
        $createResponse->assertJsonPath('data.stage', 'lead');

        $dealId = $createResponse->getBody()['data']['id'];

        $this->assertDatabaseHas('deals', [
            'id' => $dealId,
            'title' => 'Big Enterprise Deal',
            'stage' => 'lead',
        ]);

        // Verify deal shows in pipeline view
        $pipelineResponse = $this->getJson('/api/deals/pipeline');
        $pipelineResponse->assertOk();

        $pipeline = $pipelineResponse->getBody()['data'] ?? [];
        $leadStage = $this->findStageInPipeline($pipeline, 'lead');
        $this->assertNotNull($leadStage, 'Pipeline should have a lead stage');
        $this->assertGreaterThanOrEqual(1, $leadStage['count']);
    }

    public function test_create_deal_with_minimal_fields(): void
    {
        $response = $this->postJson('/api/deals', [
            'title' => 'Minimal Deal',
            'value' => 1000.00,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.title', 'Minimal Deal');
        $response->assertJsonPath('data.stage', 'lead'); // default
        $response->assertJsonPath('data.currency', 'USD'); // default
    }

    // -------------------------------------------------------
    // Show deal
    // -------------------------------------------------------

    public function test_show_deal_returns_full_details(): void
    {
        $deal = $this->createDeal('Show Test', 'qualified', 25000);

        $response = $this->getJson("/api/deals/{$deal->id}");

        $response->assertOk();
        $response->assertJsonPath('data.id', $deal->id);
        $response->assertJsonPath('data.title', 'Show Test');
        $response->assertJsonPath('data.stage', 'qualified');
    }

    public function test_show_nonexistent_deal_returns_404(): void
    {
        $response = $this->getJson('/api/deals/999999');

        $response->assertNotFound();
    }

    // -------------------------------------------------------
    // Stage transitions
    // -------------------------------------------------------

    public function test_stage_transition_from_lead_to_qualified(): void
    {
        $deal = $this->createDeal('Transition Test', 'lead', 10000);

        $response = $this->postJson("/api/deals/{$deal->id}/stage", [
            'stage' => 'qualified',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.stage', 'qualified');

        // Probability should auto-update to 25 for qualified
        $this->assertDatabaseHas('deals', [
            'id' => $deal->id,
            'stage' => 'qualified',
            'probability' => 25,
        ]);
    }

    public function test_stage_transition_through_full_pipeline(): void
    {
        $deal = $this->createDeal('Full Pipeline', 'lead', 75000);

        // lead -> qualified
        $this->postJson("/api/deals/{$deal->id}/stage", ['stage' => 'qualified'])
            ->assertOk()
            ->assertJsonPath('data.stage', 'qualified');

        // qualified -> proposal
        $this->postJson("/api/deals/{$deal->id}/stage", ['stage' => 'proposal'])
            ->assertOk()
            ->assertJsonPath('data.stage', 'proposal');

        // proposal -> negotiation
        $this->postJson("/api/deals/{$deal->id}/stage", ['stage' => 'negotiation'])
            ->assertOk()
            ->assertJsonPath('data.stage', 'negotiation');

        // negotiation -> closed_won
        $this->postJson("/api/deals/{$deal->id}/stage", ['stage' => 'closed_won'])
            ->assertOk()
            ->assertJsonPath('data.stage', 'closed_won');

        // Verify final state
        $this->assertDatabaseHas('deals', [
            'id' => $deal->id,
            'stage' => 'closed_won',
            'probability' => 100,
        ]);
    }

    public function test_stage_transition_to_closed_lost_with_reason(): void
    {
        $deal = $this->createDeal('Lost Deal', 'negotiation', 30000);

        $response = $this->postJson("/api/deals/{$deal->id}/stage", [
            'stage' => 'closed_lost',
            'lost_reason' => 'Budget constraints',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.stage', 'closed_lost');

        $this->assertDatabaseHas('deals', [
            'id' => $deal->id,
            'stage' => 'closed_lost',
            'probability' => 0,
            'lost_reason' => 'Budget constraints',
        ]);
    }

    public function test_stage_transition_to_closed_sets_actual_close_date(): void
    {
        $deal = $this->createDeal('Close Date Test', 'proposal', 20000);

        $this->postJson("/api/deals/{$deal->id}/stage", [
            'stage' => 'closed_won',
        ])->assertOk();

        $fresh = Deal::find($deal->id);
        $this->assertNotNull($fresh->actual_close_date, 'Closing a deal should set actual_close_date');
    }

    public function test_stage_transition_rejects_invalid_stage(): void
    {
        $deal = $this->createDeal('Invalid Stage', 'lead', 5000);

        $response = $this->postJson("/api/deals/{$deal->id}/stage", [
            'stage' => 'nonexistent_stage',
        ]);

        $response->assertUnprocessable();
    }

    public function test_stage_transition_requires_stage_field(): void
    {
        $deal = $this->createDeal('Missing Stage', 'lead', 5000);

        $response = $this->postJson("/api/deals/{$deal->id}/stage", []);

        $response->assertUnprocessable();
    }

    // -------------------------------------------------------
    // Pipeline view
    // -------------------------------------------------------

    public function test_pipeline_view_returns_all_stages(): void
    {
        $response = $this->getJson('/api/deals/pipeline');

        $response->assertOk();

        $pipeline = $response->getBody()['data'] ?? [];
        $stages = array_column($pipeline, 'stage');

        // All 6 stages should be present
        $this->assertContains('lead', $stages);
        $this->assertContains('qualified', $stages);
        $this->assertContains('proposal', $stages);
        $this->assertContains('negotiation', $stages);
        $this->assertContains('closed_won', $stages);
        $this->assertContains('closed_lost', $stages);
    }

    public function test_pipeline_view_groups_deals_by_stage(): void
    {
        $this->createDeal('Lead 1', 'lead', 10000);
        $this->createDeal('Lead 2', 'lead', 20000);
        $this->createDeal('Proposal 1', 'proposal', 50000);

        $response = $this->getJson('/api/deals/pipeline');
        $response->assertOk();

        $pipeline = $response->getBody()['data'] ?? [];

        $leadStage = $this->findStageInPipeline($pipeline, 'lead');
        $proposalStage = $this->findStageInPipeline($pipeline, 'proposal');

        $this->assertSame(2, $leadStage['count']);
        $this->assertSame(30000.0, $leadStage['total_value']);

        $this->assertSame(1, $proposalStage['count']);
        $this->assertSame(50000.0, $proposalStage['total_value']);
    }

    public function test_pipeline_view_empty_stages_have_zero_counts(): void
    {
        // No deals at all
        $response = $this->getJson('/api/deals/pipeline');
        $response->assertOk();

        $pipeline = $response->getBody()['data'] ?? [];
        foreach ($pipeline as $stage) {
            $this->assertSame(0, $stage['count']);
            $this->assertSame(0.0, $stage['total_value']);
        }
    }

    // -------------------------------------------------------
    // Deal update
    // -------------------------------------------------------

    public function test_update_deal_changes_value_and_title(): void
    {
        $deal = $this->createDeal('Original Title', 'lead', 10000);

        $response = $this->putJson("/api/deals/{$deal->id}", [
            'title' => 'Updated Title',
            'value' => 25000.00,
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.title', 'Updated Title');

        $this->assertDatabaseHas('deals', [
            'id' => $deal->id,
            'title' => 'Updated Title',
        ]);
    }

    // -------------------------------------------------------
    // Deal delete
    // -------------------------------------------------------

    public function test_delete_deal_returns_no_content(): void
    {
        $deal = $this->createDeal('Delete Me', 'lead', 5000);

        $response = $this->deleteJson("/api/deals/{$deal->id}");

        $response->assertNoContent();

        // Soft deleted
        $this->assertDatabaseMissing('deals', [
            'id' => $deal->id,
            'deleted_at' => null,
        ]);
    }

    // -------------------------------------------------------
    // Deal list with filters
    // -------------------------------------------------------

    public function test_list_deals_filterable_by_stage(): void
    {
        $this->createDeal('Lead Deal', 'lead', 5000);
        $this->createDeal('Won Deal', 'closed_won', 100000);

        $response = $this->getJson('/api/deals?filter[stage]=lead');

        $response->assertOk();
        $data = $response->getBody()['data'] ?? [];
        foreach ($data as $deal) {
            $this->assertSame('lead', $deal['stage']);
        }
    }

    // -------------------------------------------------------
    // Authorization
    // -------------------------------------------------------

    public function test_deal_endpoints_return_401_without_auth(): void
    {
        $this->asGuest()->getJson('/api/deals')->assertUnauthorized();
        $this->asGuest()->postJson('/api/deals', ['title' => 'X', 'value' => 1])->assertUnauthorized();
        $this->asGuest()->getJson('/api/deals/pipeline')->assertUnauthorized();
    }

    // -------------------------------------------------------
    // Validation
    // -------------------------------------------------------

    public function test_create_deal_requires_title_and_value(): void
    {
        $response = $this->postJson('/api/deals', []);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['title', 'value']);
    }

    public function test_create_deal_validates_stage_enum(): void
    {
        $response = $this->postJson('/api/deals', [
            'title' => 'Bad Stage',
            'value' => 1000,
            'stage' => 'fantasy_stage',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['stage']);
    }

    // -------------------------------------------------------
    // Helpers
    // -------------------------------------------------------

    private function createDeal(string $title, string $stage, float $value): Deal
    {
        return Deal::create([
            'title' => $title,
            'value' => $value,
            'currency' => 'USD',
            'stage' => $stage,
            'probability' => match ($stage) {
                'lead' => 10,
                'qualified' => 25,
                'proposal' => 50,
                'negotiation' => 75,
                'closed_won' => 100,
                'closed_lost' => 0,
            },
            'contact_id' => $this->contact->id,
            'company_id' => $this->company->id,
            'workspace_id' => $this->workspace->id,
            'owner_id' => $this->user->id,
        ]);
    }

    /**
     * Find a specific stage entry in the pipeline response data.
     *
     * @param array<int, array<string, mixed>> $pipeline
     * @return array<string, mixed>|null
     */
    private function findStageInPipeline(array $pipeline, string $stage): ?array
    {
        foreach ($pipeline as $entry) {
            if (($entry['stage'] ?? null) === $stage) {
                return $entry;
            }
        }
        return null;
    }
}
