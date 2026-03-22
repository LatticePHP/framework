<?php

declare(strict_types=1);

namespace Lattice\ApiKey\Tests;

use Lattice\ApiKey\ApiKeyPrincipal;
use Lattice\Contracts\Context\PrincipalInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ApiKeyPrincipalTest extends TestCase
{
    #[Test]
    public function it_implements_principal_interface(): void
    {
        $principal = new ApiKeyPrincipal(
            id: 'key-1',
            name: 'Partner API',
            scopes: ['read', 'write'],
        );

        $this->assertInstanceOf(PrincipalInterface::class, $principal);
    }

    #[Test]
    public function it_returns_api_key_type(): void
    {
        $principal = new ApiKeyPrincipal(id: 'key-1', name: 'Test');

        $this->assertSame('api_key', $principal->getType());
    }

    #[Test]
    public function it_exposes_id_and_name(): void
    {
        $principal = new ApiKeyPrincipal(id: 'key-42', name: 'Partner Key');

        $this->assertSame('key-42', $principal->getId());
        $this->assertSame('Partner Key', $principal->getName());
    }

    #[Test]
    public function it_returns_scopes(): void
    {
        $principal = new ApiKeyPrincipal(
            id: 'key-1',
            name: 'Test',
            scopes: ['read', 'write'],
        );

        $this->assertSame(['read', 'write'], $principal->getScopes());
        $this->assertTrue($principal->hasScope('read'));
        $this->assertFalse($principal->hasScope('admin'));
    }

    #[Test]
    public function it_returns_empty_roles(): void
    {
        $principal = new ApiKeyPrincipal(id: 'key-1', name: 'Test');

        $this->assertSame([], $principal->getRoles());
        $this->assertFalse($principal->hasRole('admin'));
    }

    #[Test]
    public function it_exposes_metadata(): void
    {
        $principal = new ApiKeyPrincipal(
            id: 'key-1',
            name: 'Test',
            scopes: [],
            metadata: ['partner' => 'acme'],
        );

        $this->assertSame(['partner' => 'acme'], $principal->getMetadata());
    }
}
