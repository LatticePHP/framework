<?php

declare(strict_types=1);

namespace Lattice\Authorization\Tests\Unit;

use Lattice\Auth\Principal;
use Lattice\Authorization\TenantAwareChecker;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TenantAwareCheckerTest extends TestCase
{
    #[Test]
    public function it_allows_when_tenant_matches_claim(): void
    {
        $checker = new TenantAwareChecker();
        $principal = new Principal(id: 1, type: 'user', claims: ['tenant_id' => 'acme']);

        $this->assertTrue($checker->checkTenant($principal, 'acme'));
    }

    #[Test]
    public function it_denies_when_tenant_does_not_match(): void
    {
        $checker = new TenantAwareChecker();
        $principal = new Principal(id: 1, type: 'user', claims: ['tenant_id' => 'acme']);

        $this->assertFalse($checker->checkTenant($principal, 'other'));
    }

    #[Test]
    public function it_allows_when_tenant_id_is_null(): void
    {
        $checker = new TenantAwareChecker();
        $principal = new Principal(id: 1, type: 'user');

        $this->assertTrue($checker->checkTenant($principal, null));
    }

    #[Test]
    public function it_denies_when_principal_has_no_tenant_claim_but_tenant_required(): void
    {
        $checker = new TenantAwareChecker();
        $principal = new Principal(id: 1, type: 'user');

        $this->assertFalse($checker->checkTenant($principal, 'acme'));
    }

    #[Test]
    public function it_allows_super_tenant_role(): void
    {
        $checker = new TenantAwareChecker(superTenantRole: 'super-admin');
        $principal = new Principal(id: 1, type: 'user', roles: ['super-admin'], claims: ['tenant_id' => 'acme']);

        $this->assertTrue($checker->checkTenant($principal, 'other'));
    }

    #[Test]
    public function it_uses_custom_claim_key(): void
    {
        $checker = new TenantAwareChecker(tenantClaimKey: 'org_id');
        $principal = new Principal(id: 1, type: 'user', claims: ['org_id' => 'corp']);

        $this->assertTrue($checker->checkTenant($principal, 'corp'));
        $this->assertFalse($checker->checkTenant($principal, 'other'));
    }
}
