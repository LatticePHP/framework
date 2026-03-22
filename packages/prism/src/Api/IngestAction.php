<?php

declare(strict_types=1);

namespace Lattice\Prism\Api;

use Lattice\Prism\Auth\ApiKeyAuthenticator;
use Lattice\Prism\Database\IssueRepository;
use Lattice\Prism\Event\ErrorEvent;
use Lattice\Prism\Fingerprint\Fingerprinter;
use Lattice\Prism\Storage\StorageInterface;
use Lattice\Routing\Attributes\Controller;
use Lattice\Routing\Attributes\Post;

#[Controller('/api/v1')]
final class IngestAction
{
    public function __construct(
        private readonly StorageInterface $storage,
        private readonly Fingerprinter $fingerprinter,
        private readonly IssueRepository $issueRepository,
        private readonly ApiKeyAuthenticator $authenticator,
    ) {}

    /**
     * POST /api/v1/events — Ingest single or batch error events.
     *
     * The endpoint is designed for speed: blob write first (append), then DB upsert.
     *
     * @param array<string, mixed> $body The decoded JSON request body.
     * @param array<string, string> $headers Request headers.
     * @return array<string, mixed>
     */
    #[Post('/events')]
    public function __invoke(array $body, array $headers = []): array
    {
        // Authenticate
        $apiKey = $this->authenticator->extractKey($headers);
        if ($apiKey === null) {
            return self::errorResponse(401, 'Missing API key. Use X-Prism-Key header or Authorization: Bearer.');
        }

        $project = $this->authenticator->authenticate($apiKey);
        if ($project === null) {
            return self::errorResponse(403, 'Invalid API key.');
        }

        // Detect batch vs single
        $isBatch = isset($body[0]) && is_array($body[0]);
        $events = $isBatch ? $body : [$body];

        // Enforce batch limit
        if (count($events) > 100) {
            return self::errorResponse(422, 'Batch size exceeds maximum of 100 events.');
        }

        $accepted = [];
        $rejected = [];

        foreach ($events as $index => $eventData) {
            if (!is_array($eventData)) {
                $rejected[] = ['index' => $index, 'error' => 'Event must be an object'];
                continue;
            }

            // Inject project_id from the authenticated project
            $eventData['project_id'] = $project->id;

            // Validate
            $errors = ErrorEvent::validateAndGetErrors($eventData);
            if ($errors !== []) {
                $rejected[] = ['index' => $index, 'error' => implode('; ', $errors)];
                continue;
            }

            try {
                $event = ErrorEvent::fromArray($eventData);

                // Step 1: Write blob FIRST (fast append) — never block on DB
                $blobResult = $this->storage->store($event);

                // Step 2: Generate fingerprint
                $fingerprint = $this->fingerprinter->generate($event);

                // Step 3: Upsert issue in DB
                $issueResult = $this->issueRepository->findOrCreateByFingerprint(
                    $project->id,
                    $fingerprint,
                    $event,
                );

                $accepted[] = [
                    'event_id' => $event->eventId,
                    'issue_id' => $issueResult['issue']->id,
                    'is_new' => $issueResult['is_new'],
                    'is_regression' => $issueResult['is_regression'],
                ];
            } catch (\Throwable $e) {
                $rejected[] = ['index' => $index, 'error' => $e->getMessage()];
            }
        }

        if ($isBatch) {
            return [
                'status' => 202,
                'accepted' => $accepted,
                'rejected' => $rejected,
            ];
        }

        // Single event
        if ($rejected !== []) {
            return self::errorResponse(422, $rejected[0]['error']);
        }

        return [
            'status' => 202,
            'event_ids' => array_map(static fn(array $a): string => $a['event_id'], $accepted),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function errorResponse(int $status, string $message): array
    {
        return [
            'status' => $status,
            'error' => $message,
        ];
    }
}
