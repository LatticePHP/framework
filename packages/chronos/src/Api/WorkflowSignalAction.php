<?php

declare(strict_types=1);

namespace Lattice\Chronos\Api;

use Lattice\Chronos\ChronosEventStoreInterface;
use Lattice\Contracts\Workflow\WorkflowStatus;
use Lattice\Http\Request;
use Lattice\Http\Response;
use Lattice\Workflow\Runtime\WorkflowRuntime;

/**
 * POST /api/chronos/workflows/:id/signal — send a signal to a running workflow.
 */
final class WorkflowSignalAction
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

        // Validate workflow is in a signalable state
        $signalableStates = [WorkflowStatus::Running, WorkflowStatus::Completed];
        if (!in_array($execution->getStatus(), $signalableStates, true)) {
            return Response::json(
                [
                    'type' => 'https://httpstatuses.io/409',
                    'title' => 'Conflict',
                    'status' => 409,
                    'detail' => "Workflow is in '{$execution->getStatus()->value}' state and cannot receive signals.",
                ],
                409,
            );
        }

        $body = $request->json();

        if (!is_array($body) || !isset($body['signal'])) {
            return Response::json(
                [
                    'type' => 'https://httpstatuses.io/422',
                    'title' => 'Unprocessable Entity',
                    'status' => 422,
                    'detail' => 'Request body must contain a "signal" field.',
                ],
                422,
            );
        }

        $signalName = $body['signal'];
        $payload = $body['payload'] ?? null;

        try {
            $this->runtime->signalWorkflow($execution->getWorkflowId(), $signalName, $payload);
        } catch (\RuntimeException $e) {
            return Response::json(
                [
                    'type' => 'https://httpstatuses.io/400',
                    'title' => 'Bad Request',
                    'status' => 400,
                    'detail' => $e->getMessage(),
                ],
                400,
            );
        }

        return Response::json([
            'data' => [
                'id' => $execution->getId(),
                'signal' => $signalName,
                'status' => 'delivered',
            ],
        ]);
    }
}
