<?php

declare(strict_types=1);

namespace Lattice\Prism\Api;

use Lattice\Prism\Database\IssueRepository;
use Lattice\Routing\Attributes\Controller;
use Lattice\Routing\Attributes\Get;

#[Controller('/api/prism')]
final class IssueListAction
{
    public function __construct(
        private readonly IssueRepository $issueRepository,
    ) {}

    /**
     * GET /api/prism/issues — List issues with filtering, sorting, and pagination.
     *
     * @param array<string, mixed> $query Query parameters.
     * @return array<string, mixed>
     */
    #[Get('/issues')]
    public function __invoke(array $query = []): array
    {
        $projectId = (string) ($query['project_id'] ?? $query['project'] ?? '');
        if ($projectId === '') {
            return [
                'status' => 422,
                'error' => 'project_id is required',
            ];
        }

        $filters = [];
        if (isset($query['status']) && $query['status'] !== '') {
            $filters['status'] = (string) $query['status'];
        }
        if (isset($query['level']) && $query['level'] !== '') {
            $filters['level'] = (string) $query['level'];
        }
        if (isset($query['environment']) && $query['environment'] !== '') {
            $filters['environment'] = (string) $query['environment'];
        }
        if (isset($query['platform']) && $query['platform'] !== '') {
            $filters['platform'] = (string) $query['platform'];
        }
        if (isset($query['search']) && $query['search'] !== '') {
            $filters['search'] = (string) $query['search'];
        }

        $sortBy = (string) ($query['sort'] ?? 'last_seen');
        $sortDir = (string) ($query['dir'] ?? 'desc');
        $limit = max(1, min(100, (int) ($query['limit'] ?? 25)));
        $offset = max(0, (int) ($query['offset'] ?? 0));

        $issues = $this->issueRepository->listByProject(
            $projectId,
            $filters,
            $sortBy,
            $sortDir,
            $limit,
            $offset,
        );

        $total = $this->issueRepository->countByProject($projectId, $filters);

        return [
            'status' => 200,
            'data' => array_map(
                static fn($issue): array => $issue->toArray(),
                $issues,
            ),
            'meta' => [
                'total' => $total,
                'limit' => $limit,
                'offset' => $offset,
            ],
        ];
    }
}
