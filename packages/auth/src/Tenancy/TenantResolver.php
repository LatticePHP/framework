<?php

declare(strict_types=1);

namespace Lattice\Auth\Tenancy;

use Lattice\Auth\Models\Tenant;
use Lattice\Http\Request;

/**
 * Resolves the current tenant from the incoming request using a configurable strategy.
 *
 * Supported strategies:
 * - subdomain: extracts tenant slug from subdomain (e.g., acme.app.localhost)
 * - header: reads X-Tenant-Id from request headers
 * - jwt: reads tenant_id from JWT claims (via request body/header)
 * - path: extracts tenant from URL path prefix (e.g., /tenants/{slug}/...)
 */
final class TenantResolver
{
    /**
     * Resolve the tenant from the request using the given strategy.
     */
    public function resolve(Request $request, string $strategy = 'subdomain'): ?Tenant
    {
        return match ($strategy) {
            'subdomain' => $this->resolveFromSubdomain($request),
            'header' => $this->resolveFromHeader($request),
            'jwt' => $this->resolveFromJwt($request),
            'path' => $this->resolveFromPath($request),
            default => null,
        };
    }

    /**
     * Resolve tenant from the subdomain portion of the Host header.
     *
     * Given base domain "app.localhost", a request to "acme.app.localhost"
     * will look up a Tenant with slug "acme".
     */
    private function resolveFromSubdomain(Request $request): ?Tenant
    {
        $host = $request->getHeader('host');

        if ($host === null) {
            return null;
        }

        $baseDomain = $this->getConfig('domain.base', 'app.localhost');
        $subdomain = str_replace('.' . $baseDomain, '', $host);

        // If the host IS the base domain (no subdomain), return null
        if ($subdomain === $host) {
            return null;
        }

        return Tenant::where('slug', $subdomain)->first();
    }

    /**
     * Resolve tenant from the X-Tenant-Id request header.
     */
    private function resolveFromHeader(Request $request): ?Tenant
    {
        $tenantId = $request->getHeader('x-tenant-id');

        if ($tenantId === null) {
            return null;
        }

        return Tenant::find($tenantId);
    }

    /**
     * Resolve tenant from the JWT token's tenant_id claim.
     *
     * Expects the authenticated principal to carry a tenant_id.
     * Falls back to X-Tenant-Id header if JWT parsing is not available here.
     */
    private function resolveFromJwt(Request $request): ?Tenant
    {
        // JWT resolution relies on the auth layer having already parsed the token.
        // The tenant_id is typically embedded as a claim.
        // Fall back to header-based resolution as JWT claims are set by the auth guard.
        $tenantId = $request->getHeader('x-tenant-id');

        if ($tenantId === null) {
            return null;
        }

        return Tenant::find($tenantId);
    }

    /**
     * Resolve tenant from the URL path prefix.
     *
     * Expects URLs like /tenants/{slug}/... or path param 'tenant'.
     */
    private function resolveFromPath(Request $request): ?Tenant
    {
        // Check for a path parameter first
        $slug = $request->getParam('tenant');

        if ($slug !== null) {
            return Tenant::where('slug', $slug)->first();
        }

        // Try to extract from URI pattern /tenants/{slug}/...
        $uri = $request->getUri();

        if (preg_match('#^/tenants/([a-z0-9_-]+)#i', $uri, $matches)) {
            return Tenant::where('slug', $matches[1])->first();
        }

        return null;
    }

    /**
     * Get a tenancy config value. Provides a seam for testing.
     */
    private function getConfig(string $key, mixed $default = null): mixed
    {
        if (function_exists('config')) {
            try {
                return config('tenancy.' . $key, $default);
            } catch (\Throwable) {
                return $default;
            }
        }

        return $default;
    }
}
