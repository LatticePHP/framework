<?php

declare(strict_types=1);

namespace App\Modules\Dashboard;

use App\Models\Activity;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Deal;

final class DashboardService
{
    /**
     * Get overview statistics for the CRM dashboard.
     *
     * @return array<string, mixed>
     */
    public function getStats(): array
    {
        return [
            'contacts' => [
                'total' => Contact::count(),
                'by_status' => array_combine(
                    Contact::STATUSES,
                    array_map(
                        fn (string $status): int => Contact::where('status', $status)->count(),
                        Contact::STATUSES,
                    ),
                ),
            ],
            'companies' => [
                'total' => Company::count(),
            ],
            'deals' => [
                'total' => Deal::count(),
                'total_value' => (float) Deal::sum('value'),
                'open_value' => (float) Deal::whereNotIn('stage', Deal::CLOSED_STAGES)->sum('value'),
                'won_value' => (float) Deal::where('stage', 'closed_won')->sum('value'),
                'by_stage' => $this->getDealsByStage(),
            ],
            'activities' => [
                'total' => Activity::count(),
                'upcoming' => Activity::whereNull('completed_at')
                    ->where('due_date', '>=', now())
                    ->count(),
                'overdue' => Activity::whereNull('completed_at')
                    ->where('due_date', '<', now())
                    ->count(),
                'completed' => Activity::whereNotNull('completed_at')->count(),
            ],
        ];
    }

    /**
     * Get pipeline overview: deals grouped by stage with totals.
     *
     * @return array<string, mixed>
     */
    public function getPipelineOverview(): array
    {
        $stages = Deal::STAGES;
        $pipeline = [];

        foreach ($stages as $stage) {
            $deals = Deal::where('stage', $stage)->get();
            $pipeline[] = [
                'stage' => $stage,
                'count' => $deals->count(),
                'total_value' => (float) $deals->sum('value'),
                'avg_value' => $deals->count() > 0 ? round((float) $deals->avg('value'), 2) : 0,
            ];
        }

        return $pipeline;
    }

    /**
     * Get recent activities for the dashboard feed.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getRecentActivities(int $limit = 10): array
    {
        return Activity::with(['contact', 'deal', 'owner'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(fn (Activity $a) => [
                'id' => $a->id,
                'type' => $a->type,
                'subject' => $a->subject,
                'contact' => $a->contact ? ['id' => $a->contact->id, 'name' => $a->contact->full_name] : null,
                'deal' => $a->deal ? ['id' => $a->deal->id, 'title' => $a->deal->title] : null,
                'owner' => $a->owner ? ['id' => $a->owner->id, 'name' => $a->owner->name] : null,
                'is_completed' => $a->isCompleted(),
                'created_at' => $a->created_at?->toIso8601String(),
            ])
            ->toArray();
    }

    /**
     * @return array<string, array{count: int, value: float}>
     */
    private function getDealsByStage(): array
    {
        $result = [];

        foreach (Deal::STAGES as $stage) {
            $deals = Deal::where('stage', $stage);
            $result[$stage] = [
                'count' => $deals->count(),
                'value' => (float) $deals->sum('value'),
            ];
        }

        return $result;
    }
}
