<?php

declare(strict_types=1);

namespace Lattice\Chronos\Api;

use Lattice\Chronos\ChronosEventStoreInterface;
use Lattice\Contracts\Workflow\WorkflowEventType;
use Lattice\Http\Request;
use Lattice\Http\Response;

/**
 * GET /api/chronos/workflows/:id/events — paginated event history timeline.
 */
final class WorkflowEventsAction
{
    public function __construct(
        private readonly ChronosEventStoreInterface $eventStore,
    ) {}

    public function __invoke(Request $request): Response
    {
        $id = $request->getParam('id');

        if ($id === null || $id === '') {
            return Response::json(
                [
                    'type' => 'https://httpstatuses.io/400',
                    'title' => 'Bad Request',
                    'status' => 400,
                    'detail' => 'Missing workflow execution ID.',
                ],
                400,
            );
        }

        $execution = $this->eventStore->getExecution($id);

        if ($execution === null) {
            return Response::json(
                [
                    'type' => 'https://httpstatuses.io/404',
                    'title' => 'Not Found',
                    'status' => 404,
                    'detail' => "Workflow execution not found: {$id}",
                ],
                404,
            );
        }

        $events = $this->eventStore->getEvents($id);

        // Filter by event type: ?event_type=ActivityCompleted,ActivityFailed
        $eventTypeFilter = $request->getQuery('event_type');
        if ($eventTypeFilter !== null && $eventTypeFilter !== '') {
            $allowedTypes = explode(',', $eventTypeFilter);
            $events = array_filter(
                $events,
                fn ($event) => in_array($event->getEventType()->value, $allowedTypes, true),
            );
            $events = array_values($events);
        }

        // Ordering: ?order=desc (default asc)
        $order = $request->getQuery('order') ?? 'asc';
        if ($order === 'desc') {
            $events = array_reverse($events);
        }

        $total = count($events);

        // Pagination: ?page=1&per_page=50
        $page = max(1, (int) ($request->getQuery('page') ?? 1));
        $perPage = max(1, min(100, (int) ($request->getQuery('per_page') ?? 50)));
        $offset = ($page - 1) * $perPage;

        $paginatedEvents = array_slice($events, $offset, $perPage);
        $hasMore = ($page * $perPage) < $total;

        $data = array_map(function ($event, $index) use ($events, $offset) {
            $durationMs = null;
            $eventIndex = $offset + $index;

            // Calculate duration from previous event if available
            if ($eventIndex > 0 && isset($events[$eventIndex - 1])) {
                $prevTimestamp = $events[$eventIndex - 1]->getTimestamp();
                $currentTimestamp = $event->getTimestamp();
                $durationMs = ($currentTimestamp->getTimestamp() - $prevTimestamp->getTimestamp()) * 1000;
            }

            return [
                'sequence' => $event->getSequenceNumber(),
                'type' => $event->getEventType()->value,
                'timestamp' => $event->getTimestamp()->format('c'),
                'data' => $event->getPayload(),
                'duration_ms' => $durationMs,
            ];
        }, $paginatedEvents, array_keys($paginatedEvents));

        return Response::json([
            'data' => $data,
            'meta' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'has_more' => $hasMore,
            ],
        ]);
    }
}
