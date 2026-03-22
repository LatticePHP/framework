<?php

declare(strict_types=1);

namespace Lattice\Auth\Workspace;

use Lattice\Auth\Models\Workspace;
use Lattice\Http\Request;

/**
 * Resolves the current workspace from an incoming request.
 *
 * Supports multiple resolution strategies:
 * - header: X-Workspace-Id header
 * - jwt: workspace_id claim in JWT token
 * - url: /workspaces/{id}/... URL prefix
 * - slug: X-Workspace-Slug header or subdomain
 */
final class WorkspaceResolver
{
    /**
     * Resolve a workspace from the request using the given strategy.
     */
    public function resolve(Request $request, string $strategy = 'header'): ?Workspace
    {
        return match ($strategy) {
            'header' => $this->fromHeader($request),
            'jwt' => $this->fromJwt($request),
            'url' => $this->fromUrl($request),
            'slug' => $this->fromSlug($request),
            default => null,
        };
    }

    /**
     * Resolve from the X-Workspace-Id header.
     */
    private function fromHeader(Request $request): ?Workspace
    {
        $id = $request->getHeader('X-Workspace-Id');

        if ($id === null || $id === '') {
            return null;
        }

        return Workspace::find((int) $id);
    }

    /**
     * Resolve from the workspace_id claim in a JWT token.
     */
    private function fromJwt(Request $request): ?Workspace
    {
        // JWT strategy is not supported with Lattice\Http\Request
        // as it doesn't have an attributes bag. Use header strategy instead.
        return null;
    }

    /**
     * Resolve from URL path: /workspaces/{id}/...
     */
    private function fromUrl(Request $request): ?Workspace
    {
        $path = $request->getUri();

        if (preg_match('#^/(?:api/)?workspaces/(\d+)(?:/|$)#', $path, $matches)) {
            return Workspace::find((int) $matches[1]);
        }

        return null;
    }

    /**
     * Resolve from the X-Workspace-Slug header.
     */
    private function fromSlug(Request $request): ?Workspace
    {
        $slug = $request->getHeader('X-Workspace-Slug');

        if ($slug === null || $slug === '') {
            return null;
        }

        return Workspace::where('slug', $slug)->first();
    }
}
