<?php

declare(strict_types=1);

namespace Lattice\Authorization\Tests\Unit;

use Lattice\Auth\Principal;
use Lattice\Authorization\PolicyRegistry;
use Lattice\Contracts\Auth\PolicyInterface;
use Lattice\Contracts\Context\PrincipalInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PolicyRegistryTest extends TestCase
{
    #[Test]
    public function it_registers_and_checks_policy(): void
    {
        $policy = new class implements PolicyInterface {
            public function can(PrincipalInterface $principal, string $ability, mixed $subject = null): bool
            {
                return $ability === 'view';
            }
        };

        $registry = new PolicyRegistry();
        $registry->register('posts', $policy);

        $principal = new Principal(id: 1, type: 'user');

        $this->assertTrue($registry->can($principal, 'posts.view'));
        $this->assertFalse($registry->can($principal, 'posts.delete'));
    }

    #[Test]
    public function it_returns_false_for_unregistered_resource(): void
    {
        $registry = new PolicyRegistry();
        $principal = new Principal(id: 1, type: 'user');

        $this->assertFalse($registry->can($principal, 'unknown.view'));
    }

    #[Test]
    public function it_passes_subject_to_policy(): void
    {
        $policy = new class implements PolicyInterface {
            public function can(PrincipalInterface $principal, string $ability, mixed $subject = null): bool
            {
                return $subject === 'owned-post';
            }
        };

        $registry = new PolicyRegistry();
        $registry->register('posts', $policy);

        $principal = new Principal(id: 1, type: 'user');

        $this->assertTrue($registry->can($principal, 'posts.edit', 'owned-post'));
        $this->assertFalse($registry->can($principal, 'posts.edit', 'other-post'));
    }

    #[Test]
    public function it_handles_ability_without_dot_separator(): void
    {
        $registry = new PolicyRegistry();
        $principal = new Principal(id: 1, type: 'user');

        $this->assertFalse($registry->can($principal, 'simple-ability'));
    }
}
