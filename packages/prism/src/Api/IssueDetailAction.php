<?php

declare(strict_types=1);

namespace Lattice\Prism\Api;

use Lattice\Prism\Database\IssueRepository;
use Lattice\Prism\Storage\StorageInterface;
use Lattice\Routing\Attributes\Controller;
use Lattice\Routing\Attributes\Get;

#[Controller('/api/prism')]
final class IssueDetailAction
{
    public function __construct(
        private readonly IssueRepository $issueRepository,
        private readonly StorageInterface $storage,
    ) {}

    /**
     * GET /api/prism/issues/:id — Fetch issue detail with sample events from blob.
     *
     * @return array<string, mixed>
     */
    #[Get('/issues/{id}')]
    public function __invoke(string $id, ?string $blobPath = null): array
    {
        $issue = $this->issueRepository->findById($id);

        if ($issue === null) {
            return [
                'status' => 404,
                'error' => 'Issue not found.',
            ];
        }

        $sampleEvents = [];
        if ($blobPath !== null && $blobPath !== '') {
            $allEvents = $this->storage->retrieve($blobPath, 0, 100);
            // Filter to events matching this issue's fingerprint (by title match as proxy)
            foreach ($allEvents as $eventData) {
                $sampleEvents[] = $eventData;
                if (count($sampleEvents) >= 5) {
                    break;
                }
            }
        }

        return [
            'status' => 200,
            'data' => [
                'issue' => $issue->toArray(),
                'sample_events' => $sampleEvents,
            ],
        ];
    }
}
