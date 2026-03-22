<?php

declare(strict_types=1);

namespace Lattice\Auth\Tenancy;

use Lattice\Contracts\Context\ExecutionContextInterface;
use Lattice\Contracts\Pipeline\GuardInterface;

/**
 * Pipeline guard that resolves the current tenant and sets the global TenantContext.
 *
 * If no tenant can be resolved from the request, the guard rejects the request.
 * This ensures all downstream code runs within a tenant scope.
 *
 * Usage: apply as a guard to routes or modules that require tenant context.
 */
final class TenantGuard implements GuardInterface
{
    public function __construct(
        private readonly TenantResolver $resolver,
        private readonly string $strategy = 'subdomain',
    ) {}

    public function canActivate(ExecutionContextInterface $context): bool
    {
        // The context must expose a request for tenant resolution
        if (!method_exists($context, 'getRequest')) {
            return false;
        }

        $request = $context->getRequest();
        $tenant = $this->resolver->resolve($request, $this->strategy);

        if ($tenant === null) {
            return false;
        }

        // Set tenant in global context for downstream use
        TenantContext::set($tenant);

        return true;
    }
}
