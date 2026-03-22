<?php

declare(strict_types=1);

namespace Lattice\Observability\Health;

enum HealthStatus: string
{
    case Up = 'up';
    case Down = 'down';
    case Degraded = 'degraded';
}
