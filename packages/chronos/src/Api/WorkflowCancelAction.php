<?php

declare(strict_types=1);

namespace Lattice\Chronos\Api;

use Lattice\Chronos\ChronosEventStoreInterface;
use Lattice\Contracts\Workflow\WorkflowStatus;
use Lattice\Http\Request;
use Lattice\Http\Response;
use Lattice\Workflow\Runtime\WorkflowRuntime;

/**
 * POST /api/chronos/workflows/:id/cancel — cancel a running workflow.
 */
final class WorkflowCancelAction
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

        $cancellableStates = [WorkflowStatus::Running, WorkflowStatus::Completed];
        if (!in_array($execution->getStatus(), $cancellableStates, true)) {
            return Response::json(
                [
                    'type' => 'https://httpstatuses.io/409',
                    'title' => 'Conflict',
                    'status' => 409,
                    'detail' => "Workflow cannot be cancelled in '{$execution->getStatus()->value}' state.",
                ],
                409,
            );
        }

        $this->runtime->cancelWorkflow($execution->getWorkflowId());

        return Response::json([
            'data' => [
                'id' => $execution->getId(),
                'status' => 'cancelled',
                'message' => 'Workflow cancellation dispatched.',
            ],
        ]);
    }
}
