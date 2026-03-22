<?php

declare(strict_types=1);

namespace App\Http;

use Lattice\Routing\Attributes\Controller;
use Lattice\Routing\Attributes\Get;

#[Controller('/health')]
final class HealthController
{
    #[Get('/')]
    public function check(): array
    {
        return [
            'status' => 'ok',
            'timestamp' => (new \DateTimeImmutable())->format(\DateTimeInterface::RFC3339),
        ];
    }
}
