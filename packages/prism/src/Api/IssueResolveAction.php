<?php

declare(strict_types=1);

namespace Lattice\Prism\Api;

use Lattice\Prism\Database\IssueRepository;
use Lattice\Prism\Event\IssueStatus;
use Lattice\Routing\Attributes\Controller;
use Lattice\Routing\Attributes\Post;

#[Controller('/api/prism')]
final class IssueResolveAction
{
    public function __construct(
        private readonly IssueRepository $issueRepository,
    ) {}

    /**
     * POST /api/prism/issues/:id/resolve — Resolve or update issue status.
     *
     * @param array<string, mixed> $body Optional body with 'status' key.
     * @return array<string, mixed>
     */
    #[Post('/issues/{id}/resolve')]
    public function __invoke(string $id, array $body = []): array
    {
        $issue = $this->issueRepository->findById($id);

        if ($issue === null) {
            return [
                'status' => 404,
                'error' => 'Issue not found.',
            ];
        }

        $statusValue = (string) ($body['status'] ?? 'resolved');
        $status = IssueStatus::tryFrom($statusValue);

        if ($status === null) {
            return [
                'status' => 422,
                'error' => sprintf(
                    'Invalid status. Must be one of: %s',
                    implode(', ', array_map(
                        static fn(IssueStatus $s): string => $s->value,
                        IssueStatus::cases(),
                    )),
                ),
            ];
        }

        $updated = $this->issueRepository->updateStatus($id, $status);

        return [
            'status' => 200,
            'data' => $updated?->toArray(),
        ];
    }
}
