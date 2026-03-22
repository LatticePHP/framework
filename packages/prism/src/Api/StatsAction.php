<?php

declare(strict_types=1);

namespace Lattice\Prism\Api;

use Lattice\Prism\Database\IssueRepository;
use Lattice\Prism\Event\IssueStatus;
use Lattice\Routing\Attributes\Controller;
use Lattice\Routing\Attributes\Get;

#[Controller('/api/prism')]
final class StatsAction
{
    public function __construct(
        private readonly IssueRepository $issueRepository,
    ) {}

    /**
     * GET /api/prism/stats — Aggregate statistics.
     *
     * @param array<string, mixed> $query Query parameters.
     * @return array<string, mixed>
     */
    #[Get('/stats')]
    public function __invoke(array $query = []): array
    {
        $projectId = (string) ($query['project_id'] ?? $query['project'] ?? '');

        $allIssues = $projectId !== ''
            ? $this->issueRepository->listByProject($projectId, limit: PHP_INT_MAX)
            : $this->issueRepository->all();

        $totalIssues = count($allIssues);
        $unresolvedCount = 0;
        $resolvedCount = 0;
        $ignoredCount = 0;
        $totalEvents = 0;

        /** @var array<string, int> $levelCounts */
        $levelCounts = [];

        foreach ($allIssues as $issue) {
            $totalEvents += $issue->count;

            match ($issue->status) {
                IssueStatus::Unresolved => $unresolvedCount++,
                IssueStatus::Resolved => $resolvedCount++,
                IssueStatus::Ignored => $ignoredCount++,
            };

            $level = $issue->level->value;
            $levelCounts[$level] = ($levelCounts[$level] ?? 0) + 1;
        }

        return [
            'status' => 200,
            'data' => [
                'total_issues' => $totalIssues,
                'unresolved' => $unresolvedCount,
                'resolved' => $resolvedCount,
                'ignored' => $ignoredCount,
                'total_events' => $totalEvents,
                'by_level' => $levelCounts,
            ],
        ];
    }
}
