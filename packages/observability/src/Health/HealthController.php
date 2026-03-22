<?php

declare(strict_types=1);

namespace Lattice\Observability\Health;

use Lattice\Routing\Attributes\Controller;
use Lattice\Routing\Attributes\Get;

#[Controller('/health')]
final class HealthController
{
    public function __construct(
        private readonly HealthRegistry $health,
    ) {}

    #[Get('/')]
    public function check(): array
    {
        return $this->health->toArray();
    }

    #[Get('/live')]
    public function liveness(): array
    {
        return ['status' => 'up'];
    }

    #[Get('/ready')]
    public function readiness(): array
    {
        $result = $this->health->toArray();

        if ($result['status'] !== HealthStatus::Up->value) {
            throw new \RuntimeException('Service not ready: ' . $result['status']);
        }

        return $result;
    }
}
