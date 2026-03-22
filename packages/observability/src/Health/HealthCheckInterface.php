<?php

declare(strict_types=1);

namespace Lattice\Observability\Health;

interface HealthCheckInterface
{
    public function getName(): string;

    public function check(): HealthResult;
}
