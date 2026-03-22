<?php

declare(strict_types=1);

namespace Lattice\Core\Tests\Unit;

use Lattice\Core\Lifecycle\LifecycleManager;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class LifecycleManagerTest extends TestCase
{
    private LifecycleManager $lifecycle;

    protected function setUp(): void
    {
        $this->lifecycle = new LifecycleManager();
    }

    #[Test]
    public function pre_boot_hooks_execute_in_order(): void
    {
        $log = [];

        $this->lifecycle->onPreBoot(function () use (&$log) { $log[] = 'first'; });
        $this->lifecycle->onPreBoot(function () use (&$log) { $log[] = 'second'; });

        $this->lifecycle->executePreBoot();

        $this->assertSame(['first', 'second'], $log);
    }

    #[Test]
    public function boot_hooks_execute_in_order(): void
    {
        $log = [];

        $this->lifecycle->onBoot(function () use (&$log) { $log[] = 'a'; });
        $this->lifecycle->onBoot(function () use (&$log) { $log[] = 'b'; });

        $this->lifecycle->executeBoot();

        $this->assertSame(['a', 'b'], $log);
    }

    #[Test]
    public function ready_hooks_execute_in_order(): void
    {
        $log = [];

        $this->lifecycle->onReady(function () use (&$log) { $log[] = 'x'; });
        $this->lifecycle->onReady(function () use (&$log) { $log[] = 'y'; });

        $this->lifecycle->executeReady();

        $this->assertSame(['x', 'y'], $log);
    }

    #[Test]
    public function terminate_hooks_execute_in_order(): void
    {
        $log = [];

        $this->lifecycle->onTerminate(function () use (&$log) { $log[] = 'cleanup1'; });
        $this->lifecycle->onTerminate(function () use (&$log) { $log[] = 'cleanup2'; });

        $this->lifecycle->executeTerminate();

        $this->assertSame(['cleanup1', 'cleanup2'], $log);
    }

    #[Test]
    public function full_lifecycle_executes_phases_in_correct_order(): void
    {
        $log = [];

        $this->lifecycle->onPreBoot(function () use (&$log) { $log[] = 'preBoot'; });
        $this->lifecycle->onBoot(function () use (&$log) { $log[] = 'boot'; });
        $this->lifecycle->onReady(function () use (&$log) { $log[] = 'ready'; });
        $this->lifecycle->onTerminate(function () use (&$log) { $log[] = 'terminate'; });

        $this->lifecycle->executePreBoot();
        $this->lifecycle->executeBoot();
        $this->lifecycle->executeReady();
        $this->lifecycle->executeTerminate();

        $this->assertSame(['preBoot', 'boot', 'ready', 'terminate'], $log);
    }

    #[Test]
    public function hooks_are_not_executed_twice(): void
    {
        $count = 0;

        $this->lifecycle->onBoot(function () use (&$count) { $count++; });

        $this->lifecycle->executeBoot();
        $this->lifecycle->executeBoot();

        $this->assertSame(1, $count);
    }

    #[Test]
    public function no_hooks_registered_executes_without_error(): void
    {
        $this->lifecycle->executePreBoot();
        $this->lifecycle->executeBoot();
        $this->lifecycle->executeReady();
        $this->lifecycle->executeTerminate();

        $this->assertTrue(true); // No exception thrown
    }
}
