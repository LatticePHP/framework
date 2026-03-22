<?php

declare(strict_types=1);

namespace Lattice\ApiKey\Tests;

use Lattice\ApiKey\ApiKeyAuthGuard;
use Lattice\ApiKey\ApiKeyManager;
use Lattice\ApiKey\ApiKeyPrincipal;
use Lattice\ApiKey\Store\InMemoryApiKeyStore;
use Lattice\Contracts\Auth\AuthGuardInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ApiKeyAuthGuardTest extends TestCase
{
    private ApiKeyAuthGuard $guard;
    private ApiKeyManager $manager;

    protected function setUp(): void
    {
        $store = new InMemoryApiKeyStore();
        $this->manager = new ApiKeyManager($store, 'lk');
        $this->guard = new ApiKeyAuthGuard($this->manager);
    }

    #[Test]
    public function it_implements_auth_guard_interface(): void
    {
        $this->assertInstanceOf(AuthGuardInterface::class, $this->guard);
    }

    #[Test]
    public function it_supports_api_key_type(): void
    {
        $this->assertTrue($this->guard->supports('api-key'));
        $this->assertFalse($this->guard->supports('jwt'));
        $this->assertFalse($this->guard->supports('pat'));
    }

    #[Test]
    public function it_authenticates_via_x_api_key_header(): void
    {
        $result = $this->manager->create('Test Key', ['read']);

        $principal = $this->guard->authenticate(['x-api-key' => $result->plainKey]);

        $this->assertInstanceOf(ApiKeyPrincipal::class, $principal);
        $this->assertSame($result->keyId, $principal->getId());
    }

    #[Test]
    public function it_authenticates_via_query_param(): void
    {
        $result = $this->manager->create('Test Key', ['read']);

        $principal = $this->guard->authenticate(['api_key' => $result->plainKey]);

        $this->assertInstanceOf(ApiKeyPrincipal::class, $principal);
        $this->assertSame($result->keyId, $principal->getId());
    }

    #[Test]
    public function it_prefers_header_over_query_param(): void
    {
        $result1 = $this->manager->create('Header Key', ['read']);
        $result2 = $this->manager->create('Query Key', ['write']);

        $principal = $this->guard->authenticate([
            'x-api-key' => $result1->plainKey,
            'api_key' => $result2->plainKey,
        ]);

        $this->assertSame($result1->keyId, $principal->getId());
    }

    #[Test]
    public function it_returns_null_for_missing_key(): void
    {
        $this->assertNull($this->guard->authenticate([]));
    }

    #[Test]
    public function it_returns_null_for_invalid_key(): void
    {
        $this->assertNull($this->guard->authenticate(['x-api-key' => 'lk_invalid']));
    }

    #[Test]
    public function it_returns_null_for_non_array_credentials(): void
    {
        $this->assertNull($this->guard->authenticate('not-an-array'));
    }
}
