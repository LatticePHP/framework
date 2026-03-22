<?php

declare(strict_types=1);

namespace Lattice\Loom;

use Lattice\Http\Request;
use Lattice\Http\Response;

/**
 * Middleware guard for Loom admin routes.
 *
 * Supports a custom authorization callback. Default behaviour:
 *   - In development: allow all requests
 *   - In production: deny all requests (must configure a callback)
 */
final class LoomAdminGuard
{
    /** @var (callable(Request): bool)|null */
    private $authCallback;

    private string $environment;

    /**
     * @param (callable(Request): bool)|null $authCallback
     */
    public function __construct(?callable $authCallback = null, string $environment = 'production')
    {
        $this->authCallback = $authCallback;
        $this->environment = $environment;
    }

    public function __invoke(Request $request, callable $next): Response
    {
        if (!$this->isAuthorized($request)) {
            return Response::error('Unauthorized', 403);
        }

        return $next($request);
    }

    public function isAuthorized(Request $request): bool
    {
        if ($this->authCallback !== null) {
            return ($this->authCallback)($request);
        }

        // Default: allow in development, deny in production
        return $this->environment !== 'production';
    }
}
