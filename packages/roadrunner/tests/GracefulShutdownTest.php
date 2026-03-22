<?php

declare(strict_types=1);

namespace Lattice\RoadRunner\Tests;

use Lattice\RoadRunner\GracefulShutdown;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class GracefulShutdownTest extends TestCase
{
    #[Test]
    public function it_defaults_to_not_stopping(): void
    {
        $shutdown = new GracefulShutdown();

        $this->assertFalse($shutdown->shouldStop());
    }

    #[Test]
    public function it_can_be_triggered_to_stop(): void
    {
        $shutdown = new GracefulShutdown();
        $shutdown->trigger();

        $this->assertTrue($shutdown->shouldStop());
    }

    #[Test]
    public function it_registers_signal_handlers_without_error(): void
    {
        $shutdown = new GracefulShutdown();

        // On Windows or environments without pcntl, register() should not throw
        $shutdown->register();

        $this->assertFalse($shutdown->shouldStop());
    }

    #[Test]
    public function it_remains_stopped_after_trigger(): void
    {
        $shutdown = new GracefulShutdown();
        $shutdown->trigger();

        $this->assertTrue($shutdown->shouldStop());
        $this->assertTrue($shutdown->shouldStop());
    }

    #[Test]
    public function it_can_be_reset(): void
    {
        $shutdown = new GracefulShutdown();
        $shutdown->trigger();

        $this->assertTrue($shutdown->shouldStop());

        $shutdown->reset();

        $this->assertFalse($shutdown->shouldStop());
    }
}
