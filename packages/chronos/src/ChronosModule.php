<?php

declare(strict_types=1);

namespace Lattice\Chronos;

use Lattice\Chronos\Http\ChronosAdminGuard;
use Lattice\Chronos\Http\ChronosController;
use Lattice\Module\Attribute\Module;

/**
 * Chronos module — workflow execution dashboard API.
 *
 * Registers all Chronos API routes and applies the admin guard.
 *
 * Routes:
 *   GET  /api/chronos/workflows          — paginated workflow list
 *   GET  /api/chronos/workflows/:id      — workflow detail
 *   GET  /api/chronos/workflows/:id/events — event history
 *   POST /api/chronos/workflows/:id/signal — send signal
 *   POST /api/chronos/workflows/:id/retry  — retry failed workflow
 *   POST /api/chronos/workflows/:id/cancel — cancel running workflow
 *   GET  /api/chronos/stats              — aggregate stats
 *   GET  /api/chronos/stream             — SSE real-time stream
 */
#[Module(
    controllers: [ChronosController::class],
    providers: [ChronosAdminGuard::class],
)]
final class ChronosModule
{
    /**
     * @return list<array{method: string, path: string, handler: string}>
     */
    public static function routes(): array
    {
        return [
            ['method' => 'GET', 'path' => '/api/chronos/workflows', 'handler' => 'list'],
            ['method' => 'GET', 'path' => '/api/chronos/workflows/{id}', 'handler' => 'detail'],
            ['method' => 'GET', 'path' => '/api/chronos/workflows/{id}/events', 'handler' => 'events'],
            ['method' => 'POST', 'path' => '/api/chronos/workflows/{id}/signal', 'handler' => 'signal'],
            ['method' => 'POST', 'path' => '/api/chronos/workflows/{id}/retry', 'handler' => 'retry'],
            ['method' => 'POST', 'path' => '/api/chronos/workflows/{id}/cancel', 'handler' => 'cancel'],
            ['method' => 'GET', 'path' => '/api/chronos/stats', 'handler' => 'stats'],
        ];
    }
}
