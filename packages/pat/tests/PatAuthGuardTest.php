<?php

declare(strict_types=1);

namespace Lattice\Pat\Tests;

use Lattice\Auth\Principal;
use Lattice\Contracts\Auth\AuthGuardInterface;
use Lattice\Contracts\Context\PrincipalInterface;
use Lattice\Pat\PatAuthGuard;
use Lattice\Pat\PatManager;
use Lattice\Pat\Store\InMemoryPatStore;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PatAuthGuardTest extends TestCase
{
    private PatAuthGuard $guard;
    private PatManager $manager;

    protected function setUp(): void
    {
        $store = new InMemoryPatStore();
        $this->manager = new PatManager($store);
        $this->guard = new PatAuthGuard($this->manager);
    }

    #[Test]
    public function it_implements_auth_guard_interface(): void
    {
        $this->assertInstanceOf(AuthGuardInterface::class, $this->guard);
    }

    #[Test]
    public function it_supports_pat_type(): void
    {
        $this->assertTrue($this->guard->supports('pat'));
        $this->assertFalse($this->guard->supports('jwt'));
        $this->assertFalse($this->guard->supports('api-key'));
    }

    #[Test]
    public function it_authenticates_valid_bearer_token(): void
    {
        $principal = new Principal(id: 'user-99', type: 'user', scopes: ['read']);
        $result = $this->manager->create($principal, 'Test Token', ['read']);

        $resolved = $this->guard->authenticate(['authorization' => "Bearer {$result->plainToken}"]);

        $this->assertInstanceOf(PrincipalInterface::class, $resolved);
        $this->assertSame('user-99', $resolved->getId());
    }

    #[Test]
    public function it_returns_null_for_missing_authorization(): void
    {
        $this->assertNull($this->guard->authenticate([]));
    }

    #[Test]
    public function it_returns_null_for_non_bearer_scheme(): void
    {
        $this->assertNull($this->guard->authenticate(['authorization' => 'Basic abc123']));
    }

    #[Test]
    public function it_returns_null_for_invalid_token(): void
    {
        $this->assertNull($this->guard->authenticate(['authorization' => 'Bearer invalid-token']));
    }

    #[Test]
    public function it_returns_null_for_non_array_credentials(): void
    {
        $this->assertNull($this->guard->authenticate('not-an-array'));
    }
}
