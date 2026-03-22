<?php

declare(strict_types=1);

namespace App\Modules\Dashboard;

use Lattice\Auth\JwtAuthenticationGuard;
use Lattice\Auth\Workspace\WorkspaceGuard;
use Lattice\Http\Request;
use Lattice\Http\Response;
use Lattice\Http\ResponseFactory;
use Lattice\Pipeline\Attributes\UseGuards;
use Lattice\Routing\Attributes\Controller;
use Lattice\Routing\Attributes\Get;

#[Controller('/api/dashboard')]
#[UseGuards(guards: [JwtAuthenticationGuard::class, WorkspaceGuard::class])]
final class DashboardController
{
    public function __construct(
        private readonly DashboardService $service,
    ) {}

    /**
     * Get CRM overview statistics.
     */
    #[Get('/stats')]
    public function stats(): Response
    {
        return ResponseFactory::json([
            'data' => $this->service->getStats(),
        ]);
    }

    /**
     * Get pipeline overview for deals.
     */
    #[Get('/pipeline')]
    public function pipeline(): Response
    {
        return ResponseFactory::json([
            'data' => $this->service->getPipelineOverview(),
        ]);
    }

    /**
     * Get recent activity feed.
     */
    #[Get('/feed')]
    public function feed(Request $request): Response
    {
        $limit = (int) ($request->getQuery('limit') ?? 10);

        return ResponseFactory::json([
            'data' => $this->service->getRecentActivities($limit),
        ]);
    }
}
