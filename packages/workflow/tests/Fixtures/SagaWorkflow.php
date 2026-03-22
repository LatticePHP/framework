<?php

declare(strict_types=1);

namespace Lattice\Workflow\Tests\Fixtures;

use Lattice\Workflow\Attributes\Workflow;
use Lattice\Workflow\Compensation\CompensationScope;
use Lattice\Workflow\Runtime\WorkflowContext;

#[Workflow]
final class SagaWorkflow
{
    public function execute(WorkflowContext $ctx, array $input): array
    {
        $scope = new CompensationScope();
        $results = [];

        $paymentId = $scope->run(
            fn () => $ctx->executeActivity(PaymentActivity::class, 'charge', $input['amount']),
            fn () => $ctx->executeActivity(PaymentActivity::class, 'refund', $results['payment'] ?? ''),
        );
        $results['payment'] = $paymentId;

        $trackingId = $scope->run(
            fn () => $ctx->executeActivity(ShippingActivity::class, 'ship', $input['address']),
            fn () => $ctx->executeActivity(ShippingActivity::class, 'cancelShipment', $results['shipping'] ?? ''),
        );
        $results['shipping'] = $trackingId;

        return $results;
    }
}
