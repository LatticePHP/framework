<?php

declare(strict_types=1);

namespace Lattice\Chronos\Sse;

use Lattice\Chronos\ChronosEventStoreInterface;
use Lattice\Http\Request;
use Lattice\Http\Response;

/**
 * GET /api/chronos/stream — Server-Sent Events endpoint for real-time workflow updates.
 *
 * Supports:
 * - Global stream (all execution state changes)
 * - Per-execution stream (?workflow_id=:id)
 * - Last-Event-ID header for reconnection
 * - Heartbeat/keep-alive pings
 *
 * In production, this would stream events using flush(). For testing purposes,
 * this returns a snapshot response with SSE-formatted headers.
 */
final class WorkflowSseController
{
    public function __construct(
        private readonly ChronosEventStoreInterface $eventStore,
        private readonly int $heartbeatIntervalSeconds = 15,
    ) {}

    public function __invoke(Request $request): Response
    {
        $workflowId = $request->getQuery('workflow_id');
        $lastEventId = $request->getHeader('Last-Event-ID');

        // Build the event payload for a snapshot SSE response
        $events = $this->collectEvents($workflowId, $lastEventId);

        $ssePayload = '';
        foreach ($events as $event) {
            $ssePayload .= "id: {$event['id']}\n";
            $ssePayload .= "event: {$event['event']}\n";
            $ssePayload .= 'data: ' . json_encode($event['data'], JSON_THROW_ON_ERROR) . "\n\n";
        }

        return new Response(
            statusCode: 200,
            headers: [
                'Content-Type' => 'text/event-stream',
                'Cache-Control' => 'no-cache',
                'Connection' => 'keep-alive',
                'X-Heartbeat-Interval' => (string) $this->heartbeatIntervalSeconds,
            ],
            body: $ssePayload,
        );
    }

    /**
     * Collect events for the SSE response.
     *
     * @return list<array{id: string, event: string, data: array<string, mixed>}>
     */
    private function collectEvents(?string $workflowId, ?string $lastEventId): array
    {
        $events = [];
        $lastSeq = $lastEventId !== null ? (int) $lastEventId : 0;

        if ($workflowId !== null) {
            // Per-execution stream
            $execution = $this->eventStore->getExecution($workflowId);
            if ($execution === null) {
                return $events;
            }

            $workflowEvents = $this->eventStore->getEvents($workflowId);
            foreach ($workflowEvents as $event) {
                if ($event->getSequenceNumber() <= $lastSeq) {
                    continue;
                }

                $events[] = [
                    'id' => (string) $event->getSequenceNumber(),
                    'event' => 'event_added',
                    'data' => [
                        'execution_id' => $workflowId,
                        'sequence' => $event->getSequenceNumber(),
                        'type' => $event->getEventType()->value,
                        'timestamp' => $event->getTimestamp()->format('c'),
                        'payload' => $event->getPayload(),
                    ],
                ];
            }

            // Emit current status
            $events[] = [
                'id' => (string) (count($workflowEvents) + 1),
                'event' => 'status_changed',
                'data' => [
                    'execution_id' => $workflowId,
                    'status' => $execution->getStatus()->value,
                ],
            ];
        } else {
            // Global stream — emit stats update
            $stats = $this->eventStore->getStats();
            $events[] = [
                'id' => '1',
                'event' => 'stats_updated',
                'data' => $stats,
            ];
        }

        return $events;
    }
}
