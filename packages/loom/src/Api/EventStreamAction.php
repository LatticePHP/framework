<?php

declare(strict_types=1);

namespace Lattice\Loom\Api;

use Lattice\Http\Request;
use Lattice\Http\Response;
use Lattice\Loom\Metrics\MetricsStore;

/**
 * GET /api/loom/events - Server-Sent Events endpoint.
 *
 * Returns an SSE-formatted response with appropriate headers.
 * In a real streaming environment (RoadRunner, OpenSwoole), this would
 * keep the connection open. For FPM, it returns a snapshot of pending events.
 *
 * Event types:
 *   - job.processed: { id, class, queue, runtime_ms }
 *   - job.failed: { id, class, queue, exception, message }
 *   - metrics.snapshot: { throughput, failure_rate, avg_runtime, active_workers }
 *   - worker.status: { worker_id, status, queue, memory_mb }
 *   - queue.size: { queue, size }
 *
 * Supports Last-Event-ID header for reconnection.
 */
final class EventStreamAction
{
    private int $eventIdCounter = 0;

    public function __construct(
        private readonly MetricsStore $store,
    ) {}

    public function __invoke(Request $request): Response
    {
        $lastEventId = $request->getHeader('Last-Event-ID');

        $events = $this->buildEventPayload($lastEventId);

        $body = $this->formatSsePayload($events);

        return new Response(
            statusCode: 200,
            headers: [
                'Content-Type' => 'text/event-stream',
                'Cache-Control' => 'no-cache',
                'Connection' => 'keep-alive',
                'X-Accel-Buffering' => 'no',
            ],
            body: $body,
        );
    }

    /**
     * Build the set of SSE events to send.
     *
     * @return array<int, array{id: int, event: string, data: array<string, mixed>}>
     */
    private function buildEventPayload(?string $lastEventId): array
    {
        $events = [];

        // Always include a metrics snapshot
        $snapshot = $this->store->getSnapshot('1h');
        $events[] = [
            'id' => ++$this->eventIdCounter,
            'event' => 'metrics.snapshot',
            'data' => [
                'throughput' => $snapshot->throughputPerMinute,
                'failure_rate' => $snapshot->totalProcessed > 0
                    ? round($snapshot->totalFailed / ($snapshot->totalProcessed + $snapshot->totalFailed) * 100, 2)
                    : 0.0,
                'avg_runtime' => $snapshot->averageRuntimeMs,
                'active_workers' => $snapshot->activeWorkers,
            ],
        ];

        // Include worker statuses
        foreach ($this->store->getWorkers() as $workerId => $worker) {
            $events[] = [
                'id' => ++$this->eventIdCounter,
                'event' => 'worker.status',
                'data' => [
                    'worker_id' => $workerId,
                    'status' => $worker['status'],
                    'queue' => $worker['queue'],
                    'memory_mb' => $worker['memory_mb'],
                ],
            ];
        }

        // Include queue sizes
        foreach ($this->store->getQueueSizes() as $queue => $size) {
            $events[] = [
                'id' => ++$this->eventIdCounter,
                'event' => 'queue.size',
                'data' => [
                    'queue' => $queue,
                    'size' => $size,
                ],
            ];
        }

        return $events;
    }

    /**
     * Format events into SSE text format.
     *
     * @param array<int, array{id: int, event: string, data: array<string, mixed>}> $events
     */
    private function formatSsePayload(array $events): string
    {
        $output = ": heartbeat\n\n";

        foreach ($events as $event) {
            $output .= 'id: ' . $event['id'] . "\n";
            $output .= 'event: ' . $event['event'] . "\n";
            $output .= 'data: ' . json_encode($event['data'], JSON_THROW_ON_ERROR) . "\n\n";
        }

        return $output;
    }
}
