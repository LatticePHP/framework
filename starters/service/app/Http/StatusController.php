<?php

declare(strict_types=1);

namespace App\Http;

use Lattice\Routing\Attributes\Controller;
use Lattice\Routing\Attributes\Get;

#[Controller('/')]
final class StatusController
{
    #[Get('/health')]
    public function health(): array
    {
        return [
            'status' => 'ok',
            'service' => 'order-service',
            'timestamp' => (new \DateTimeImmutable())->format(\DateTimeInterface::RFC3339),
        ];
    }

    #[Get('/status')]
    public function status(): array
    {
        return [
            'service' => 'order-service',
            'version' => '1.0.0',
            'uptime' => time(),
            'handlers' => [
                'order.created',
                'order.paid',
                'order.shipped',
                'order.cancel',
            ],
        ];
    }
}
