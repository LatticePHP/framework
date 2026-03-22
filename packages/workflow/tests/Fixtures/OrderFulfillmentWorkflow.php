<?php

declare(strict_types=1);

namespace Lattice\Workflow\Tests\Fixtures;

use Lattice\Workflow\Attributes\QueryMethod;
use Lattice\Workflow\Attributes\SignalMethod;
use Lattice\Workflow\Attributes\UpdateMethod;
use Lattice\Workflow\Attributes\Workflow;
use Lattice\Workflow\Runtime\WorkflowContext;

#[Workflow]
final class OrderFulfillmentWorkflow
{
    private string $status = 'pending';

    public function execute(WorkflowContext $ctx, array $input): array
    {
        $paymentResult = $ctx->executeActivity(
            PaymentActivity::class,
            'charge',
            $input['amount'],
        );
        $this->status = 'charged';

        $shipResult = $ctx->executeActivity(
            ShippingActivity::class,
            'ship',
            $input['address'],
        );
        $this->status = 'shipped';

        return ['payment' => $paymentResult, 'shipping' => $shipResult];
    }

    #[SignalMethod]
    public function markDelivered(): void
    {
        $this->status = 'delivered';
    }

    #[QueryMethod]
    public function getStatus(): string
    {
        return $this->status;
    }

    #[UpdateMethod]
    public function updateAddress(string $newAddress): string
    {
        return 'Address updated to: ' . $newAddress;
    }
}
