<?php

declare(strict_types=1);

namespace Lattice\Workflow\Tests\Fixtures;

use Lattice\Workflow\Attributes\Activity;

#[Activity]
final class ShippingActivity
{
    public function ship(string $address): string
    {
        return 'tracking_' . md5($address);
    }

    public function cancelShipment(string $trackingId): bool
    {
        return true;
    }
}
