<?php

declare(strict_types=1);

namespace Lattice\Module\Tests\Integration;

use Lattice\Contracts\Container\ContainerInterface;
use Lattice\Core\Support\ServiceProvider;
use Lattice\Module\ModuleBootstrapper;
use Lattice\Module\ModuleDefinition;
use Lattice\Module\ModuleRegistry;
use Lattice\Module\Provider\ProviderRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ModuleBootstrapper::class)]
final class ServiceProviderTest extends TestCase
{
    private InMemoryContainer $container;

    protected function setUp(): void
    {
        $this->container = new InMemoryContainer();
    }

    #[Test]
    public function service_provider_register_binds_services(): void
    {
        $registry = new ModuleRegistry();
        $registry->register('TestModule', new ModuleDefinition(
            providers: [GreetingServiceProvider::class],
        ));

        $providerRegistry = new ProviderRegistry();
        $bootstrapper = new ModuleBootstrapper($providerRegistry);
        $bootstrapper->boot($registry, $this->container);

        self::assertTrue($this->container->has('greeting'));
        self::assertSame('Hello, World!', $this->container->get('greeting'));
    }

    #[Test]
    public function service_provider_boot_can_access_other_services(): void
    {
        $registry = new ModuleRegistry();
        $registry->register('CoreModule', new ModuleDefinition(
            providers: [GreetingServiceProvider::class],
        ));
        $registry->register('AppModule', new ModuleDefinition(
            imports: ['CoreModule'],
            providers: [FormattedGreetingServiceProvider::class],
        ));

        $providerRegistry = new ProviderRegistry();
        $bootstrapper = new ModuleBootstrapper($providerRegistry);
        $bootstrapper->boot($registry, $this->container);

        self::assertTrue($this->container->has('formatted_greeting'));
        self::assertSame('[Hello, World!]', $this->container->get('formatted_greeting'));
    }

    #[Test]
    public function register_called_before_boot(): void
    {
        $registry = new ModuleRegistry();
        $registry->register('TestModule', new ModuleDefinition(
            providers: [OrderTrackingServiceProvider::class],
        ));

        $providerRegistry = new ProviderRegistry();
        $bootstrapper = new ModuleBootstrapper($providerRegistry);
        $bootstrapper->boot($registry, $this->container);

        /** @var list<string> $order */
        $order = $this->container->get('lifecycle_order');

        self::assertSame(['register', 'boot'], $order);
    }

    #[Test]
    public function multiple_providers_all_register_before_any_boot(): void
    {
        $registry = new ModuleRegistry();
        $registry->register('TestModule', new ModuleDefinition(
            providers: [
                FirstTrackingProvider::class,
                SecondTrackingProvider::class,
            ],
        ));

        // Use a shared tracker
        $this->container->instance('call_log', []);

        $providerRegistry = new ProviderRegistry();
        $bootstrapper = new ModuleBootstrapper($providerRegistry);
        $bootstrapper->boot($registry, $this->container);

        /** @var list<string> $log */
        $log = $this->container->get('call_log');

        self::assertSame([
            'FirstTrackingProvider::register',
            'SecondTrackingProvider::register',
            'FirstTrackingProvider::boot',
            'SecondTrackingProvider::boot',
        ], $log);
    }

    #[Test]
    public function bindings_accessible_after_boot(): void
    {
        $registry = new ModuleRegistry();
        $registry->register('TestModule', new ModuleDefinition(
            providers: [GreetingServiceProvider::class],
        ));

        $providerRegistry = new ProviderRegistry();
        $bootstrapper = new ModuleBootstrapper($providerRegistry);
        $bootstrapper->boot($registry, $this->container);

        // Binding should still be accessible
        self::assertSame('Hello, World!', $this->container->get('greeting'));

        // Provider instance should be stored
        self::assertTrue($this->container->has(GreetingServiceProvider::class));
    }

    #[Test]
    public function plain_class_providers_still_work(): void
    {
        $registry = new ModuleRegistry();
        $registry->register('TestModule', new ModuleDefinition(
            providers: ['App\\SomePlainService'],
        ));

        $providerRegistry = new ProviderRegistry();
        $bootstrapper = new ModuleBootstrapper($providerRegistry);
        $bootstrapper->boot($registry, $this->container);

        // Plain classes get bound via ProviderRegistry
        $providers = $providerRegistry->getProvidersForModule('TestModule');
        self::assertContains('App\\SomePlainService', $providers);
    }
}

/**
 * A minimal in-memory container for integration testing.
 */
final class InMemoryContainer implements ContainerInterface
{
    /** @var array<string, mixed> */
    private array $instances = [];

    /** @var array<string, callable> */
    private array $bindings = [];

    public function bind(string $abstract, mixed $concrete = null): void
    {
        if ($concrete instanceof \Closure || is_callable($concrete)) {
            $this->bindings[$abstract] = $concrete;
        } else {
            $this->instances[$abstract] = $concrete ?? $abstract;
        }
    }

    public function singleton(string $abstract, mixed $concrete = null): void
    {
        $this->bind($abstract, $concrete);
    }

    public function instance(string $abstract, mixed $instance): void
    {
        $this->instances[$abstract] = $instance;
    }

    public function make(string $abstract, array $parameters = []): mixed
    {
        if (array_key_exists($abstract, $this->instances)) {
            return $this->instances[$abstract];
        }

        if (isset($this->bindings[$abstract])) {
            $result = ($this->bindings[$abstract])($this);
            return $result;
        }

        throw new \RuntimeException("No binding for '{$abstract}'.");
    }

    public function get(string $id): mixed
    {
        return $this->make($id);
    }

    public function has(string $id): bool
    {
        return array_key_exists($id, $this->instances) || isset($this->bindings[$id]);
    }

    public function reset(): void
    {
        $this->instances = [];
        $this->bindings = [];
    }
}

// --- Test ServiceProvider implementations ---

final class GreetingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->container->instance('greeting', 'Hello, World!');
    }
}

final class FormattedGreetingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Register nothing during register phase
    }

    public function boot(): void
    {
        // Access a service registered by another provider
        $greeting = $this->container->get('greeting');
        $this->container->instance('formatted_greeting', "[{$greeting}]");
    }
}

final class OrderTrackingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->container->instance('lifecycle_order', ['register']);
    }

    public function boot(): void
    {
        /** @var list<string> $order */
        $order = $this->container->get('lifecycle_order');
        $order[] = 'boot';
        $this->container->instance('lifecycle_order', $order);
    }
}

final class FirstTrackingProvider extends ServiceProvider
{
    public function register(): void
    {
        /** @var list<string> $log */
        $log = $this->container->get('call_log');
        $log[] = 'FirstTrackingProvider::register';
        $this->container->instance('call_log', $log);
    }

    public function boot(): void
    {
        /** @var list<string> $log */
        $log = $this->container->get('call_log');
        $log[] = 'FirstTrackingProvider::boot';
        $this->container->instance('call_log', $log);
    }
}

final class SecondTrackingProvider extends ServiceProvider
{
    public function register(): void
    {
        /** @var list<string> $log */
        $log = $this->container->get('call_log');
        $log[] = 'SecondTrackingProvider::register';
        $this->container->instance('call_log', $log);
    }

    public function boot(): void
    {
        /** @var list<string> $log */
        $log = $this->container->get('call_log');
        $log[] = 'SecondTrackingProvider::boot';
        $this->container->instance('call_log', $log);
    }
}
