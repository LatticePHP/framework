<?php

declare(strict_types=1);

namespace Lattice\Core\Tests\Unit;

use Lattice\Core\Container\Container;
use Lattice\Core\Support\ServiceProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ServiceProviderTest extends TestCase
{
    #[Test]
    public function register_is_called_with_container(): void
    {
        $container = new Container();
        $provider = new StubServiceProvider($container);

        $provider->register();

        $this->assertTrue($container->has('stub.registered'));
        $this->assertSame('registered', $container->make('stub.registered'));
    }

    #[Test]
    public function boot_is_called_with_container(): void
    {
        $container = new Container();
        $provider = new StubServiceProvider($container);

        $provider->register();
        $provider->boot();

        $this->assertTrue($container->has('stub.booted'));
        $this->assertSame('booted', $container->make('stub.booted'));
    }

    #[Test]
    public function provider_has_access_to_container(): void
    {
        $container = new Container();
        $provider = new StubServiceProvider($container);

        $this->assertSame($container, $provider->getContainer());
    }

    #[Test]
    public function provider_with_no_boot_logic(): void
    {
        $container = new Container();
        $provider = new MinimalServiceProvider($container);

        $provider->register();
        $provider->boot();

        $this->assertTrue($container->has('minimal'));
    }
}

class StubServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->container->instance('stub.registered', 'registered');
    }

    public function boot(): void
    {
        $this->container->instance('stub.booted', 'booted');
    }

    public function getContainer(): Container
    {
        return $this->container;
    }
}

class MinimalServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->container->instance('minimal', true);
    }
}
