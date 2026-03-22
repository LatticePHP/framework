<?php

declare(strict_types=1);

namespace App\Workflows;

use App\Activities\PaymentActivity;
use App\Activities\ShippingActivity;
use Lattice\Workflow\Attributes\QueryMethod;
use Lattice\Workflow\Attributes\SignalMethod;
use Lattice\Workflow\Attributes\Workflow;
use Lattice\Workflow\Runtime\WorkflowContext;

#[Workflow(name: 'OrderFulfillment')]
final class OrderFulfillmentWorkflow
{
    private string $status = 'pending';

    public function execute(WorkflowContext $context): mixed
    {
        $this->status = 'processing_payment';
        $paymentResult = $context->executeActivity(PaymentActivity::class, 'processPayment');

        $this->status = 'shipping';
        $shippingResult = $context->executeActivity(ShippingActivity::class, 'shipOrder');

        $this->status = 'completed';

        return [
            'payment' => $paymentResult,
            'shipping' => $shippingResult,
            'status' => $this->status,
        ];
    }

    #[QueryMethod]
    public function getStatus(): string
    {
        return $this->status;
    }

    #[SignalMethod]
    public function cancel(): void
    {
        $this->status = 'cancelled';
    }
}
