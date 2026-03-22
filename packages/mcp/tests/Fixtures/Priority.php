<?php

declare(strict_types=1);

namespace Lattice\Mcp\Tests\Fixtures;

enum Priority: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
}
