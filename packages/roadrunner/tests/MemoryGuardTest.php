<?php

declare(strict_types=1);

namespace Lattice\RoadRunner\Tests;

use Lattice\RoadRunner\MemoryGuard;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MemoryGuardTest extends TestCase
{
    #[Test]
    public function it_returns_current_memory_usage_in_mb(): void
    {
        $guard = new MemoryGuard();
        $usage = $guard->getCurrentUsageMb();

        $this->assertIsFloat($usage);
        $this->assertGreaterThan(0.0, $usage);
    }

    #[Test]
    public function it_returns_false_when_under_memory_limit(): void
    {
        $guard = new MemoryGuard();

        // 10GB should be more than enough
        $this->assertFalse($guard->check(10240));
    }

    #[Test]
    public function it_returns_true_when_over_memory_limit(): void
    {
        $guard = new MemoryGuard();

        // 0 MB limit should always be exceeded
        $this->assertTrue($guard->check(0));
    }

    #[Test]
    public function it_detects_memory_usage_with_realistic_limit(): void
    {
        $guard = new MemoryGuard();
        $currentMb = $guard->getCurrentUsageMb();

        // Setting limit below current usage should trigger
        $this->assertTrue($guard->check((int) floor($currentMb)));

        // Setting limit well above current usage should not trigger
        $this->assertFalse($guard->check((int) ceil($currentMb) + 100));
    }
}
