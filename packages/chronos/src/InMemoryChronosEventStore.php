<?php

declare(strict_types=1);

namespace Lattice\Chronos;

use DateTimeImmutable;
use Lattice\Contracts\Workflow\WorkflowEventInterface;
use Lattice\Contracts\Workflow\WorkflowEventStoreInterface;
use Lattice\Contracts\Workflow\WorkflowExecutionInterface;
use Lattice\Contracts\Workflow\WorkflowStatus;
use Lattice\Workflow\WorkflowExecution;

/**
 * In-memory implementation of ChronosEventStoreInterface for testing.
 *
 * Wraps the core InMemoryEventStore and adds listing/stats capabilities.
 */
final class InMemoryChronosEventStore implements ChronosEventStoreInterface
{
    /** @var array<string, list<WorkflowEventInterface>> */
    private array $events = [];

    /** @var array<string, WorkflowExecution> */
    private array $executions = [];

    private int $executionCounter = 0;

    public function appendEvent(string $executionId, WorkflowEventInterface $event): void
    {
        if (!isset($this->executions[$executionId])) {
            throw new \RuntimeException("Execution not found: {$executionId}");
        }

        $this->events[$executionId][] = $event;
    }

    /** @return array<WorkflowEventInterface> */
    public function getEvents(string $executionId): array
    {
        return $this->events[$executionId] ?? [];
    }

    public function createExecution(string $workflowType, string $workflowId, string $runId, mixed $input): string
    {
        $this->executionCounter++;
        $executionId = 'exec_' . $this->executionCounter;

        $execution = new WorkflowExecution(
            id: $executionId,
            workflowType: $workflowType,
            workflowId: $workflowId,
            runId: $runId,
            input: $input,
            startedAt: new DateTimeImmutable(),
        );

        $this->executions[$executionId] = $execution;
        $this->events[$executionId] = [];

        return $executionId;
    }

    /**
     * Create an execution with a specific start time (for testing date range filters).
     */
    public function createExecutionWithTimestamp(
        string $workflowType,
        string $workflowId,
        string $runId,
        mixed $input,
        DateTimeImmutable $startedAt,
        ?string $parentWorkflowId = null,
    ): string {
        $this->executionCounter++;
        $executionId = 'exec_' . $this->executionCounter;

        $execution = new WorkflowExecution(
            id: $executionId,
            workflowType: $workflowType,
            workflowId: $workflowId,
            runId: $runId,
            input: $input,
            startedAt: $startedAt,
            parentWorkflowId: $parentWorkflowId,
        );

        $this->executions[$executionId] = $execution;
        $this->events[$executionId] = [];

        return $executionId;
    }

    public function updateExecutionStatus(string $executionId, WorkflowStatus $status, mixed $result = null): void
    {
        if (!isset($this->executions[$executionId])) {
            throw new \RuntimeException("Execution not found: {$executionId}");
        }

        $this->executions[$executionId]->setStatus($status);

        if ($result !== null) {
            $this->executions[$executionId]->setResult($result);
        }
    }

    public function getExecution(string $executionId): ?WorkflowExecutionInterface
    {
        return $this->executions[$executionId] ?? null;
    }

    public function findExecutionByWorkflowId(string $workflowId): ?WorkflowExecutionInterface
    {
        foreach ($this->executions as $execution) {
            if ($execution->getWorkflowId() === $workflowId) {
                return $execution;
            }
        }

        return null;
    }

    public function listExecutions(array $filters = []): array
    {
        $executions = array_values($this->executions);

        // Filter by status
        if (!empty($filters['status'])) {
            $statuses = $filters['status'];
            $executions = array_filter(
                $executions,
                fn (WorkflowExecution $e) => in_array($e->getStatus()->value, $statuses, true),
            );
        }

        // Filter by type
        if (!empty($filters['type'])) {
            $type = $filters['type'];
            $executions = array_filter(
                $executions,
                fn (WorkflowExecution $e) => $e->getWorkflowType() === $type,
            );
        }

        // Filter by date range
        if (!empty($filters['from'])) {
            $from = new DateTimeImmutable($filters['from']);
            $executions = array_filter(
                $executions,
                fn (WorkflowExecution $e) => $e->getStartedAt() >= $from,
            );
        }

        if (!empty($filters['to'])) {
            $to = new DateTimeImmutable($filters['to'] . ' 23:59:59');
            $executions = array_filter(
                $executions,
                fn (WorkflowExecution $e) => $e->getStartedAt() <= $to,
            );
        }

        // Search by workflow ID
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $executions = array_filter(
                $executions,
                fn (WorkflowExecution $e) => str_contains($e->getWorkflowId(), $search)
                    || str_contains($e->getId(), $search),
            );
        }

        $executions = array_values($executions);

        // Sort
        $sort = $filters['sort'] ?? 'started_at';
        $order = $filters['order'] ?? 'desc';

        usort($executions, function (WorkflowExecution $a, WorkflowExecution $b) use ($sort, $order): int {
            $cmp = match ($sort) {
                'type' => strcmp($a->getWorkflowType(), $b->getWorkflowType()),
                'status' => strcmp($a->getStatus()->value, $b->getStatus()->value),
                'workflow_id' => strcmp($a->getWorkflowId(), $b->getWorkflowId()),
                default => $a->getStartedAt() <=> $b->getStartedAt(),
            };

            return $order === 'asc' ? $cmp : -$cmp;
        });

        $total = count($executions);

        // Paginate
        $page = max(1, $filters['page'] ?? 1);
        $perPage = max(1, min(100, $filters['per_page'] ?? 20));
        $offset = ($page - 1) * $perPage;

        $executions = array_slice($executions, $offset, $perPage);

        return [
            'data' => $executions,
            'total' => $total,
        ];
    }

    public function getStats(): array
    {
        $running = 0;
        $completed = 0;
        $failed = 0;
        $cancelled = 0;
        $totalDuration = 0;
        $completedCount = 0;

        foreach ($this->executions as $execution) {
            match ($execution->getStatus()) {
                WorkflowStatus::Running => $running++,
                WorkflowStatus::Completed => $completed++,
                WorkflowStatus::Failed => $failed++,
                WorkflowStatus::Cancelled => $cancelled++,
                default => null,
            };

            if ($execution->getStatus() === WorkflowStatus::Completed && $execution->getCompletedAt() !== null) {
                $duration = ($execution->getCompletedAt()->getTimestamp() - $execution->getStartedAt()->getTimestamp()) * 1000;
                $totalDuration += $duration;
                $completedCount++;
            }
        }

        return [
            'running' => $running,
            'completed' => $completed,
            'failed' => $failed,
            'cancelled' => $cancelled,
            'avg_duration_ms' => $completedCount > 0 ? round($totalDuration / $completedCount, 2) : 0.0,
        ];
    }
}
