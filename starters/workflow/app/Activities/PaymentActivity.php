<?php

declare(strict_types=1);

namespace App\Activities;

use Lattice\Workflow\Attributes\Activity;

#[Activity(name: 'Payment')]
final class PaymentActivity
{
    public function processPayment(mixed $input = null): array
    {
        // Simulate payment processing
        return [
            'transactionId' => bin2hex(random_bytes(16)),
            'status' => 'charged',
            'amount' => $input['amount'] ?? 0,
        ];
    }
}
