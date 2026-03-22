<?php

declare(strict_types=1);

namespace App\Http;

use Lattice\Routing\Attributes\Body;
use Lattice\Routing\Attributes\Controller;
use Lattice\Routing\Attributes\Get;
use Lattice\Routing\Attributes\Param;
use Lattice\Routing\Attributes\Post;

#[Controller('/workflows')]
final class WorkflowController
{
    #[Post('/order-fulfillment')]
    public function startOrderFulfillment(#[Body] array $input): array
    {
        // In a real app, this would use WorkflowClient to start the workflow
        return [
            'workflowId' => bin2hex(random_bytes(16)),
            'type' => 'OrderFulfillment',
            'status' => 'started',
            'input' => $input,
        ];
    }

    #[Get('/order-fulfillment/{id}')]
    public function queryOrderFulfillment(#[Param] string $id): array
    {
        // In a real app, this would query the workflow via WorkflowHandle
        return [
            'workflowId' => $id,
            'type' => 'OrderFulfillment',
            'status' => 'running',
        ];
    }

    #[Post('/order-fulfillment/{id}/cancel')]
    public function cancelOrderFulfillment(#[Param] string $id): array
    {
        // In a real app, this would signal the workflow to cancel
        return [
            'workflowId' => $id,
            'status' => 'cancel_requested',
        ];
    }
}
