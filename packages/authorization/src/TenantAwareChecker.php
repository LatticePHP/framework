<?php

declare(strict_types=1);

namespace Lattice\Authorization;

use Lattice\Contracts\Context\PrincipalInterface;

final class TenantAwareChecker
{
    public function __construct(
        private readonly string $tenantClaimKey = 'tenant_id',
        private readonly ?string $superTenantRole = null,
    ) {}

    public function checkTenant(PrincipalInterface $principal, ?string $tenantId): bool
    {
        // No tenant constraint
        if ($tenantId === null) {
            return true;
        }

        // Super-tenant role bypasses check
        if ($this->superTenantRole !== null && $principal->hasRole($this->superTenantRole)) {
            return true;
        }

        // Principal must have the tenant claim matching the required tenant
        /** @var \Lattice\Auth\Principal $principal */
        if (!method_exists($principal, 'getClaim')) {
            return false;
        }

        $principalTenant = $principal->getClaim($this->tenantClaimKey);

        return $principalTenant === $tenantId;
    }
}
