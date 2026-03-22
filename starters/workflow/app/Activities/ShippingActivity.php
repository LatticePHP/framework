<?php

declare(strict_types=1);

namespace App\Activities;

use Lattice\Workflow\Attributes\Activity;

#[Activity(name: 'Shipping')]
final class ShippingActivity
{
    public function shipOrder(mixed $input = null): array
    {
        // Simulate shipping
        return [
            'trackingNumber' => strtoupper(bin2hex(random_bytes(8))),
            'carrier' => 'express',
            'estimatedDelivery' => (new \DateTimeImmutable('+3 days'))->format('Y-m-d'),
        ];
    }
}
