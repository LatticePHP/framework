<?php

declare(strict_types=1);

namespace Lattice\Chronos\Api;

use Lattice\Chronos\ChronosEventStoreInterface;
use Lattice\Http\Request;
use Lattice\Http\Response;

/**
 * GET /api/chronos/stats — aggregate workflow metrics.
 */
final class WorkflowStatsAction
{
    /** @var array{data: array<string, mixed>, expires_at: int}|null */
    private ?array $cache = null;

    private int $cacheTtlSeconds;

    public function __construct(
        private readonly ChronosEventStoreInterface $eventStore,
        int $cacheTtlSeconds = 5,
    ) {
        $this->cacheTtlSeconds = $cacheTtlSeconds;
    }

    public function __invoke(Request $request): Response
    {
        $now = time();

        // Return cached stats if still fresh
        if ($this->cache !== null && $this->cache['expires_at'] > $now) {
            return Response::json([
                'data' => $this->cache['data'],
            ]);
        }

        $stats = $this->eventStore->getStats();

        // Cache the result
        $this->cache = [
            'data' => $stats,
            'expires_at' => $now + $this->cacheTtlSeconds,
        ];

        return Response::json([
            'data' => $stats,
        ]);
    }

    /**
     * Clear the stats cache (useful for testing).
     */
    public function clearCache(): void
    {
        $this->cache = null;
    }
}
