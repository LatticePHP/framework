<?php

declare(strict_types=1);

namespace Lattice\Chronos\Api;

use Lattice\Chronos\ChronosEventStoreInterface;
use Lattice\Contracts\Workflow\WorkflowStatus;
use Lattice\Http\Request;
use Lattice\Http\Response;
use Lattice\Workflow\Runtime\WorkflowRuntime;

/**
 * POST /api/chronos/workflows/:id/retry — retry a failed workflow.
 */
final class WorkflowRetryAction
{
    public function __construct(
        private readonly ChronosEventStoreInterface $eventStore,
        private readonly WorkflowRuntime $runtime,
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

        if ($execution->getStatus() !== WorkflowStatus::Failed) {
            return Response::json(
                [
                    'type' => 'https://httpstatuses.io/409',
                    'title' => 'Conflict',
                    'status' => 409,
                    'detail' => "Only failed workflows can be retried. Current status: '{$execution->getStatus()->value}'.",
                ],
                409,
            );
        }

        // Reset status to running and re-execute
        $this->eventStore->updateExecutionStatus($id, WorkflowStatus::Running);

        try {
            $this->runtime->resumeWorkflow($id);
        } catch (\Throwable $e) {
            // The runtime will handle setting the status appropriately
        }

        // Fetch updated execution state
        $updated = $this->eventStore->getExecution($id);

        return Response::json([
            'data' => [
                'id' => $updated->getId(),
                'status' => $updated->getStatus()->value,
                'message' => 'Workflow retry initiated.',
            ],
        ]);
    }
}
