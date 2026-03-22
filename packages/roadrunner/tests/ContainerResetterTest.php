<?php

declare(strict_types=1);

namespace Lattice\RoadRunner\Tests;

use Lattice\Contracts\Container\ContainerInterface;
use Lattice\RoadRunner\ContainerResetter;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ContainerResetterTest extends TestCase
{
    #[Test]
    public function it_calls_reset_on_container(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $container->expects($this->once())->method('reset');

        $resetter = new ContainerResetter();
        $resetter->reset($container);
    }

    #[Test]
    public function it_can_reset_multiple_times(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $container->expects($this->exactly(3))->method('reset');

        $resetter = new ContainerResetter();
        $resetter->reset($container);
        $resetter->reset($container);
        $resetter->reset($container);
    }

    #[Test]
    public function it_calls_registered_reset_callbacks(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $container->expects($this->once())->method('reset');

        $callbackCalled = false;
        $resetter = new ContainerResetter();
        $resetter->addResetCallback(function () use (&$callbackCalled): void {
            $callbackCalled = true;
        });

        $resetter->reset($container);

        $this->assertTrue($callbackCalled);
    }

    #[Test]
    public function it_calls_multiple_reset_callbacks_in_order(): void
    {
        $container = $this->createMock(ContainerInterface::class);

        $order = [];
        $resetter = new ContainerResetter();
        $resetter->addResetCallback(function () use (&$order): void {
            $order[] = 'first';
        });
        $resetter->addResetCallback(function () use (&$order): void {
            $order[] = 'second';
        });

        $resetter->reset($container);

        $this->assertSame(['first', 'second'], $order);
    }
}
