<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Deal;
use Tests\TestCase;

/**
 * End-to-end tests for the dashboard endpoints.
 *
 * Covers: /api/dashboard/stats, /api/dashboard/pipeline,
 * /api/dashboard/feed, and verifies that stats reflect real data.
 */
final class DashboardTest extends TestCase
{
    // -------------------------------------------------------
    // Stats endpoint
    // -------------------------------------------------------

    public function test_stats_returns_correct_structure(): void
    {
        $response = $this->getJson('/api/dashboard/stats');

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'contacts' => ['total', 'by_status'],
                'companies' => ['total'],
                'deals' => ['total', 'total_value', 'open_value', 'won_value', 'by_stage'],
                'activities' => ['total', 'upcoming', 'overdue', 'completed'],
            ],
        ]);
    }

    public function test_stats_returns_zero_when_no_data(): void
    {
        $response = $this->getJson('/api/dashboard/stats');

        $response->assertOk();
        $data = $response->getBody()['data'];

        $this->assertSame(0, $data['contacts']['total']);
        $this->assertSame(0, $data['companies']['total']);
        $this->assertSame(0, $data['deals']['total']);
        $this->assertSame(0.0, $data['deals']['total_value']);
        $this->assertSame(0, $data['activities']['total']);
    }

    public function test_stats_reflects_real_contact_counts(): void
    {
        $this->seedContact('lead');
        $this->seedContact('lead');
        $this->seedContact('customer');
        $this->seedContact('prospect');

        $response = $this->getJson('/api/dashboard/stats');
        $response->assertOk();

        $contacts = $response->getBody()['data']['contacts'];
        $this->assertSame(4, $contacts['total']);
        $this->assertSame(2, $contacts['by_status']['lead']);
        $this->assertSame(1, $contacts['by_status']['customer']);
        $this->assertSame(1, $contacts['by_status']['prospect']);
    }

    public function test_stats_reflects_real_deal_values(): void
    {
        $contact = $this->seedContact('lead');

        // Open deals
        $this->seedDeal($contact, 'lead', 10000);
        $this->seedDeal($contact, 'proposal', 25000);

        // Won deal
        $this->seedDeal($contact, 'closed_won', 50000);

        // Lost deal
        $this->seedDeal($contact, 'closed_lost', 15000);

        $response = $this->getJson('/api/dashboard/stats');
        $response->assertOk();

        $deals = $response->getBody()['data']['deals'];
        $this->assertSame(4, $deals['total']);
        $this->assertSame(100000.0, $deals['total_value']); // 10k + 25k + 50k + 15k
        $this->assertSame(35000.0, $deals['open_value']);    // 10k + 25k (non-closed)
        $this->assertSame(50000.0, $deals['won_value']);     // 50k
    }

    public function test_stats_reflects_real_deal_counts_by_stage(): void
    {
        $contact = $this->seedContact('lead');

        $this->seedDeal($contact, 'lead', 5000);
        $this->seedDeal($contact, 'lead', 8000);
        $this->seedDeal($contact, 'qualified', 20000);
        $this->seedDeal($contact, 'closed_won', 100000);

        $response = $this->getJson('/api/dashboard/stats');
        $response->assertOk();

        $byStage = $response->getBody()['data']['deals']['by_stage'];

        $this->assertSame(2, $byStage['lead']['count']);
        $this->assertSame(13000.0, $byStage['lead']['value']); // 5k + 8k
        $this->assertSame(1, $byStage['qualified']['count']);
        $this->assertSame(20000.0, $byStage['qualified']['value']);
        $this->assertSame(1, $byStage['closed_won']['count']);
        $this->assertSame(100000.0, $byStage['closed_won']['value']);
    }

    public function test_stats_reflects_real_activity_counts(): void
    {
        $contact = $this->seedContact('lead');

        // Upcoming activity
        Activity::create([
            'type' => 'call',
            'subject' => 'Follow up call',
            'due_date' => new \DateTimeImmutable('+3 days'),
            'contact_id' => $contact->id,
            'workspace_id' => $this->workspace->id,
            'owner_id' => $this->user->id,
        ]);

        // Overdue activity
        Activity::create([
            'type' => 'meeting',
            'subject' => 'Overdue meeting',
            'due_date' => new \DateTimeImmutable('-2 days'),
            'contact_id' => $contact->id,
            'workspace_id' => $this->workspace->id,
            'owner_id' => $this->user->id,
        ]);

        // Completed activity
        Activity::create([
            'type' => 'email',
            'subject' => 'Sent proposal',
            'due_date' => new \DateTimeImmutable('-1 day'),
            'completed_at' => new \DateTimeImmutable(),
            'contact_id' => $contact->id,
            'workspace_id' => $this->workspace->id,
            'owner_id' => $this->user->id,
        ]);

        $response = $this->getJson('/api/dashboard/stats');
        $response->assertOk();

        $activities = $response->getBody()['data']['activities'];
        $this->assertSame(3, $activities['total']);
        $this->assertSame(1, $activities['upcoming']);
        $this->assertSame(1, $activities['overdue']);
        $this->assertSame(1, $activities['completed']);
    }

    public function test_stats_company_count_is_accurate(): void
    {
        Company::create([
            'name' => 'Company A',
            'domain' => 'a.com',
            'workspace_id' => $this->workspace->id,
            'owner_id' => $this->user->id,
        ]);
        Company::create([
            'name' => 'Company B',
            'domain' => 'b.com',
            'workspace_id' => $this->workspace->id,
            'owner_id' => $this->user->id,
        ]);

        $response = $this->getJson('/api/dashboard/stats');
        $response->assertOk();

        $this->assertSame(2, $response->getBody()['data']['companies']['total']);
    }

    // -------------------------------------------------------
    // Pipeline overview endpoint
    // -------------------------------------------------------

    public function test_pipeline_overview_returns_all_stages(): void
    {
        $response = $this->getJson('/api/dashboard/pipeline');

        $response->assertOk();

        $pipeline = $response->getBody()['data'] ?? [];
        $stages = array_column($pipeline, 'stage');

        $this->assertCount(6, $pipeline);
        $this->assertSame(
            ['lead', 'qualified', 'proposal', 'negotiation', 'closed_won', 'closed_lost'],
            $stages,
        );
    }

    public function test_pipeline_overview_reflects_real_data(): void
    {
        $contact = $this->seedContact('lead');

        $this->seedDeal($contact, 'lead', 10000);
        $this->seedDeal($contact, 'lead', 20000);
        $this->seedDeal($contact, 'negotiation', 75000);

        $response = $this->getJson('/api/dashboard/pipeline');
        $response->assertOk();

        $pipeline = $response->getBody()['data'] ?? [];
        $leadStage = $this->findStage($pipeline, 'lead');
        $negotiationStage = $this->findStage($pipeline, 'negotiation');
        $proposalStage = $this->findStage($pipeline, 'proposal');

        $this->assertSame(2, $leadStage['count']);
        $this->assertSame(30000.0, $leadStage['total_value']);
        $this->assertSame(15000.0, $leadStage['avg_value']); // 30k / 2

        $this->assertSame(1, $negotiationStage['count']);
        $this->assertSame(75000.0, $negotiationStage['total_value']);

        $this->assertSame(0, $proposalStage['count']);
        $this->assertSame(0.0, $proposalStage['total_value']);
        $this->assertSame(0, $proposalStage['avg_value']); // no division by zero
    }

    public function test_pipeline_overview_has_correct_structure_per_stage(): void
    {
        $response = $this->getJson('/api/dashboard/pipeline');
        $response->assertOk();

        $pipeline = $response->getBody()['data'] ?? [];
        foreach ($pipeline as $stage) {
            $this->assertArrayHasKey('stage', $stage);
            $this->assertArrayHasKey('count', $stage);
            $this->assertArrayHasKey('total_value', $stage);
            $this->assertArrayHasKey('avg_value', $stage);
        }
    }

    // -------------------------------------------------------
    // Activity feed endpoint
    // -------------------------------------------------------

    public function test_feed_returns_recent_activities(): void
    {
        $contact = $this->seedContact('customer');

        for ($i = 0; $i < 5; $i++) {
            Activity::create([
                'type' => 'call',
                'subject' => "Activity {$i}",
                'contact_id' => $contact->id,
                'workspace_id' => $this->workspace->id,
                'owner_id' => $this->user->id,
            ]);
        }

        $response = $this->getJson('/api/dashboard/feed');

        $response->assertOk();
        $data = $response->getBody()['data'] ?? [];
        $this->assertCount(5, $data);
    }

    public function test_feed_respects_limit_parameter(): void
    {
        $contact = $this->seedContact('lead');

        for ($i = 0; $i < 10; $i++) {
            Activity::create([
                'type' => 'email',
                'subject' => "Bulk Activity {$i}",
                'contact_id' => $contact->id,
                'workspace_id' => $this->workspace->id,
                'owner_id' => $this->user->id,
            ]);
        }

        $response = $this->getJson('/api/dashboard/feed?limit=3');

        $response->assertOk();
        $data = $response->getBody()['data'] ?? [];
        $this->assertCount(3, $data);
    }

    public function test_feed_returns_activity_structure(): void
    {
        $contact = $this->seedContact('lead');

        Activity::create([
            'type' => 'meeting',
            'subject' => 'Strategy meeting',
            'contact_id' => $contact->id,
            'workspace_id' => $this->workspace->id,
            'owner_id' => $this->user->id,
        ]);

        $response = $this->getJson('/api/dashboard/feed');
        $response->assertOk();

        $data = $response->getBody()['data'] ?? [];
        $this->assertNotEmpty($data);

        $activity = $data[0];
        $this->assertArrayHasKey('id', $activity);
        $this->assertArrayHasKey('type', $activity);
        $this->assertArrayHasKey('subject', $activity);
        $this->assertArrayHasKey('contact', $activity);
        $this->assertArrayHasKey('is_completed', $activity);
        $this->assertArrayHasKey('created_at', $activity);
    }

    public function test_feed_returns_empty_when_no_activities(): void
    {
        $response = $this->getJson('/api/dashboard/feed');

        $response->assertOk();
        $data = $response->getBody()['data'] ?? [];
        $this->assertCount(0, $data);
    }

    // -------------------------------------------------------
    // Authorization
    // -------------------------------------------------------

    public function test_stats_returns_401_without_auth(): void
    {
        $this->asGuest()->getJson('/api/dashboard/stats')->assertUnauthorized();
    }

    public function test_pipeline_returns_401_without_auth(): void
    {
        $this->asGuest()->getJson('/api/dashboard/pipeline')->assertUnauthorized();
    }

    public function test_feed_returns_401_without_auth(): void
    {
        $this->asGuest()->getJson('/api/dashboard/feed')->assertUnauthorized();
    }

    // -------------------------------------------------------
    // Helpers
    // -------------------------------------------------------

    private function seedContact(string $status): Contact
    {
        return Contact::create([
            'first_name' => 'Dash' . bin2hex(random_bytes(2)),
            'last_name' => 'Contact',
            'email' => 'dash-' . bin2hex(random_bytes(4)) . '@test.com',
            'status' => $status,
            'workspace_id' => $this->workspace->id,
            'owner_id' => $this->user->id,
        ]);
    }

    private function seedDeal(Contact $contact, string $stage, float $value): Deal
    {
        return Deal::create([
            'title' => 'Deal ' . bin2hex(random_bytes(3)),
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
            'contact_id' => $contact->id,
            'workspace_id' => $this->workspace->id,
            'owner_id' => $this->user->id,
            'actual_close_date' => in_array($stage, ['closed_won', 'closed_lost'], true)
                ? date('Y-m-d')
                : null,
        ]);
    }

    /**
     * @param array<int, array<string, mixed>> $pipeline
     * @return array<string, mixed>|null
     */
    private function findStage(array $pipeline, string $stage): ?array
    {
        foreach ($pipeline as $entry) {
            if (($entry['stage'] ?? null) === $stage) {
                return $entry;
            }
        }
        return null;
    }
}
