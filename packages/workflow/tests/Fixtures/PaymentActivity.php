<?php

declare(strict_types=1);

namespace Lattice\Workflow\Tests\Fixtures;

use Lattice\Workflow\Attributes\Activity;

#[Activity]
final class PaymentActivity
{
    public function charge(float $amount): string
    {
        return 'payment_' . md5((string) $amount);
    }

    public function refund(string $paymentId): string
    {
        return 'refund_' . $paymentId;
    }
}
