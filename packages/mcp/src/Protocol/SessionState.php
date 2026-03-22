<?php

declare(strict_types=1);

namespace Lattice\Mcp\Protocol;

enum SessionState: string
{
    case Connecting = 'connecting';
    case Initializing = 'initializing';
    case Ready = 'ready';
    case ShuttingDown = 'shutting_down';
    case Closed = 'closed';

    public function canTransitionTo(self $next): bool
    {
        return match ($this) {
            self::Connecting => $next === self::Initializing,
            self::Initializing => $next === self::Ready,
            self::Ready => $next === self::ShuttingDown,
            self::ShuttingDown => $next === self::Closed,
            self::Closed => false,
        };
    }
}
