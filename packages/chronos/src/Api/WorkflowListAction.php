<?php

declare(strict_types=1);

namespace Lattice\Chronos\Api;

use Lattice\Chronos\ChronosEventStoreInterface;
use Lattice\Http\Request;
use Lattice\Http\Response;

/**
 * GET /api/chronos/workflows — paginated, filterable list of workflow executions.
 */
final class WorkflowListAction
{
    public function __construct(
        private readonly ChronosEventStoreInterface $eventStore,
    ) {}

    public function __invoke(Request $request): Response
    {
        $filters = [];

        // Status filter: ?status=running,failed
        $status = $request->getQuery('status');
        if ($status !== null && $status !== '') {
            $filters['status'] = explode(',', $status);
        }

        // Type filter: ?type=OrderWorkflow
        $type = $request->getQuery('type');
        if ($type !== null && $type !== '') {
            $filters['type'] = $type;
        }

        // Date range: ?from=2026-01-01&to=2026-03-22
        $from = $request->getQuery('from');
        if ($from !== null && $from !== '') {
            $filters['from'] = $from;
        }

        $to = $request->getQuery('to');
        if ($to !== null && $to !== '') {
            $filters['to'] = $to;
        }

        // Search: ?search=wf-abc
        $search = $request->getQuery('search');
        if ($search !== null && $search !== '') {
            $filters['search'] = $search;
        }

        // Sorting: ?sort=started_at&order=desc
        $sort = $request->getQuery('sort');
        if ($sort !== null && $sort !== '') {
            $filters['sort'] = $sort;
        }

        $order = $request->getQuery('order');
        if ($order !== null && $order !== '') {
            $filters['order'] = $order;
        }

        // Pagination: ?page=1&per_page=20
        $page = $request->getQuery('page');
        if ($page !== null) {
            $filters['page'] = (int) $page;
        }

        $perPage = $request->getQuery('per_page');
        if ($perPage !== null) {
            $filters['per_page'] = (int) $perPage;
        }

        $result = $this->eventStore->listExecutions($filters);

        $currentPage = $filters['page'] ?? 1;
        $currentPerPage = $filters['per_page'] ?? 20;
        $total = $result['total'];
        $hasMore = ($currentPage * $currentPerPage) < $total;

        $data = array_map(function ($execution) {
            $events = $this->eventStore->getEvents($execution->getId());
            $lastEvent = !empty($events) ? $events[count($events) - 1] : null;

            $durationMs = null;
            if ($execution->getCompletedAt() !== null) {
                $durationMs = ($execution->getCompletedAt()->getTimestamp() - $execution->getStartedAt()->getTimestamp()) * 1000;
            }

            return [
                'id' => $execution->getId(),
                'workflow_id' => $execution->getWorkflowId(),
                'type' => $execution->getWorkflowType(),
                'status' => $execution->getStatus()->value,
                'started_at' => $execution->getStartedAt()->format('c'),
                'duration_ms' => $durationMs,
                'last_event_type' => $lastEvent?->getEventType()->value,
                'last_event_at' => $lastEvent?->getTimestamp()->format('c'),
            ];
        }, $result['data']);

        return Response::json([
            'data' => $data,
            'meta' => [
                'page' => $currentPage,
                'per_page' => $currentPerPage,
                'total' => $total,
                'has_more' => $hasMore,
            ],
        ]);
    }
}
