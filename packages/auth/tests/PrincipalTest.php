<?php

declare(strict_types=1);

namespace Lattice\Auth\Tests;

use Lattice\Auth\Principal;
use Lattice\Contracts\Context\PrincipalInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PrincipalTest extends TestCase
{
    #[Test]
    public function it_implements_principal_interface(): void
    {
        $principal = new Principal(id: 1);

        $this->assertInstanceOf(PrincipalInterface::class, $principal);
    }

    #[Test]
    public function it_returns_id(): void
    {
        $principal = new Principal(id: 42);

        $this->assertSame(42, $principal->getId());
    }

    #[Test]
    public function it_accepts_string_id(): void
    {
        $principal = new Principal(id: 'user-uuid-123');

        $this->assertSame('user-uuid-123', $principal->getId());
    }

    #[Test]
    public function it_defaults_to_user_type(): void
    {
        $principal = new Principal(id: 1);

        $this->assertSame('user', $principal->getType());
    }

    #[Test]
    public function it_accepts_custom_type(): void
    {
        $principal = new Principal(id: 1, type: 'service');

        $this->assertSame('service', $principal->getType());
    }

    #[Test]
    public function it_returns_scopes(): void
    {
        $principal = new Principal(id: 1, scopes: ['read', 'write']);

        $this->assertSame(['read', 'write'], $principal->getScopes());
    }

    #[Test]
    public function it_defaults_to_empty_scopes(): void
    {
        $principal = new Principal(id: 1);

        $this->assertSame([], $principal->getScopes());
    }

    #[Test]
    public function it_checks_has_scope(): void
    {
        $principal = new Principal(id: 1, scopes: ['read', 'write']);

        $this->assertTrue($principal->hasScope('read'));
        $this->assertTrue($principal->hasScope('write'));
        $this->assertFalse($principal->hasScope('admin'));
    }

    #[Test]
    public function it_returns_roles(): void
    {
        $principal = new Principal(id: 1, roles: ['admin', 'editor']);

        $this->assertSame(['admin', 'editor'], $principal->getRoles());
    }

    #[Test]
    public function it_defaults_to_empty_roles(): void
    {
        $principal = new Principal(id: 1);

        $this->assertSame([], $principal->getRoles());
    }

    #[Test]
    public function it_checks_has_role(): void
    {
        $principal = new Principal(id: 1, roles: ['admin', 'editor']);

        $this->assertTrue($principal->hasRole('admin'));
        $this->assertTrue($principal->hasRole('editor'));
        $this->assertFalse($principal->hasRole('superadmin'));
    }

    #[Test]
    public function it_returns_claims(): void
    {
        $claims = ['email' => 'user@example.com', 'name' => 'John'];
        $principal = new Principal(id: 1, claims: $claims);

        $this->assertSame($claims, $principal->getClaims());
    }

    #[Test]
    public function it_returns_single_claim(): void
    {
        $principal = new Principal(id: 1, claims: ['email' => 'user@example.com']);

        $this->assertSame('user@example.com', $principal->getClaim('email'));
        $this->assertNull($principal->getClaim('nonexistent'));
    }

    #[Test]
    public function it_returns_default_for_missing_claim(): void
    {
        $principal = new Principal(id: 1);

        $this->assertSame('fallback', $principal->getClaim('missing', 'fallback'));
    }
}
