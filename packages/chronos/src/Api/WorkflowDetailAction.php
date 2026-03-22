<?php

declare(strict_types=1);

namespace Lattice\Chronos\Api;

use Lattice\Chronos\ChronosEventStoreInterface;
use Lattice\Http\Request;
use Lattice\Http\Response;

/**
 * GET /api/chronos/workflows/:id — full workflow execution detail.
 */
final class WorkflowDetailAction
{
    private const int MAX_INLINE_EVENTS = 50;

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
        $inlineEvents = array_slice($events, 0, self::MAX_INLINE_EVENTS);
        $hasMoreEvents = count($events) > self::MAX_INLINE_EVENTS;

        $durationMs = null;
        if ($execution->getCompletedAt() !== null) {
            $durationMs = ($execution->getCompletedAt()->getTimestamp() - $execution->getStartedAt()->getTimestamp()) * 1000;
        }

        $eventData = array_map(fn ($event) => [
            'sequence' => $event->getSequenceNumber(),
            'type' => $event->getEventType()->value,
            'timestamp' => $event->getTimestamp()->format('c'),
            'data' => $event->getPayload(),
        ], $inlineEvents);

        return Response::json([
            'data' => [
                'id' => $execution->getId(),
                'workflow_id' => $execution->getWorkflowId(),
                'type' => $execution->getWorkflowType(),
                'run_id' => $execution->getRunId(),
                'status' => $execution->getStatus()->value,
                'input' => $execution->getInput(),
                'output' => $execution->getResult(),
                'started_at' => $execution->getStartedAt()->format('c'),
                'completed_at' => $execution->getCompletedAt()?->format('c'),
                'duration_ms' => $durationMs,
                'parent_workflow_id' => $execution->getParentWorkflowId(),
                'events' => $eventData,
                'has_more_events' => $hasMoreEvents,
                'total_events' => count($events),
            ],
        ]);
    }
}
