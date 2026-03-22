<?php

declare(strict_types=1);

namespace Lattice\Authorization\Tests\Unit;

use Lattice\Auth\Principal;
use Lattice\Authorization\Gate;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class GateTest extends TestCase
{
    #[Test]
    public function it_defines_and_checks_ability(): void
    {
        $gate = new Gate();
        $gate->define('edit-post', fn ($principal, $post) => $post === 'own');

        $principal = new Principal(id: 1, type: 'user');

        $this->assertTrue($gate->allows($principal, 'edit-post', 'own'));
        $this->assertFalse($gate->allows($principal, 'edit-post', 'other'));
    }

    #[Test]
    public function it_denies_undefined_abilities(): void
    {
        $gate = new Gate();
        $principal = new Principal(id: 1, type: 'user');

        $this->assertFalse($gate->allows($principal, 'nonexistent'));
        $this->assertTrue($gate->denies($principal, 'nonexistent'));
    }

    #[Test]
    public function denies_is_inverse_of_allows(): void
    {
        $gate = new Gate();
        $gate->define('admin', fn ($principal) => $principal->hasRole('admin'));

        $admin = new Principal(id: 1, type: 'user', roles: ['admin']);
        $regular = new Principal(id: 2, type: 'user', roles: ['user']);

        $this->assertTrue($gate->allows($admin, 'admin'));
        $this->assertFalse($gate->denies($admin, 'admin'));

        $this->assertFalse($gate->allows($regular, 'admin'));
        $this->assertTrue($gate->denies($regular, 'admin'));
    }

    #[Test]
    public function it_passes_variadic_args_to_check(): void
    {
        $gate = new Gate();
        $gate->define('transfer', fn ($principal, $amount, $currency) => $amount < 1000 && $currency === 'USD');

        $principal = new Principal(id: 1, type: 'user');

        $this->assertTrue($gate->allows($principal, 'transfer', 500, 'USD'));
        $this->assertFalse($gate->allows($principal, 'transfer', 5000, 'USD'));
        $this->assertFalse($gate->allows($principal, 'transfer', 500, 'EUR'));
    }

    #[Test]
    public function it_allows_overriding_defined_ability(): void
    {
        $gate = new Gate();
        $gate->define('action', fn () => false);
        $gate->define('action', fn () => true);

        $principal = new Principal(id: 1, type: 'user');

        $this->assertTrue($gate->allows($principal, 'action'));
    }
}
