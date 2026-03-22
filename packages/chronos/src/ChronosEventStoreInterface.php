<?php

declare(strict_types=1);

namespace Lattice\Chronos;

use Lattice\Contracts\Workflow\WorkflowEventStoreInterface;
use Lattice\Contracts\Workflow\WorkflowExecutionInterface;

/**
 * Extended event store interface that supports querying and listing
 * workflow executions for the Chronos dashboard.
 */
interface ChronosEventStoreInterface extends WorkflowEventStoreInterface
{
    /**
     * List workflow executions with filtering, sorting, and pagination.
     *
     * @param array{
     *     status?: list<string>,
     *     type?: string,
     *     from?: string,
     *     to?: string,
     *     search?: string,
     *     sort?: string,
     *     order?: string,
     *     page?: int,
     *     per_page?: int,
     * } $filters
     * @return array{
     *     data: list<WorkflowExecutionInterface>,
     *     total: int,
     * }
     */
    public function listExecutions(array $filters = []): array;

    /**
     * Get aggregate workflow statistics.
     *
     * @return array{
     *     running: int,
     *     completed: int,
     *     failed: int,
     *     cancelled: int,
     *     avg_duration_ms: float,
     * }
     */
    public function getStats(): array;
}
