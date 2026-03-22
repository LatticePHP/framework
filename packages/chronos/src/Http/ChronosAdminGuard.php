<?php

declare(strict_types=1);

namespace Lattice\Chronos\Http;

use Lattice\Http\Request;
use Lattice\Http\Response;

/**
 * Guard that restricts Chronos dashboard access to admin users.
 *
 * Accepts a configurable authorization callback. When no callback is set,
 * the guard allows all requests (open mode for development).
 */
final class ChronosAdminGuard
{
    /** @var (callable(Request): bool)|null */
    private $authorizeCallback;

    /**
     * @param (callable(Request): bool)|null $authorizeCallback
     */
    public function __construct(?callable $authorizeCallback = null)
    {
        $this->authorizeCallback = $authorizeCallback;
    }

    /**
     * Check whether the request is authorized.
     */
    public function check(Request $request): bool
    {
        if ($this->authorizeCallback === null) {
            return true;
        }

        return ($this->authorizeCallback)($request);
    }

    /**
     * Return a 403 Forbidden response when access is denied.
     */
    public function deny(): Response
    {
        return Response::json(
            [
                'type' => 'https://httpstatuses.io/403',
                'title' => 'Forbidden',
                'status' => 403,
                'detail' => 'You are not authorized to access the Chronos dashboard.',
            ],
            403,
        );
    }
}
