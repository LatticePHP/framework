<?php

declare(strict_types=1);

namespace Lattice\Module\Tests\Unit;

use Lattice\Contracts\Container\ContainerInterface;
use Lattice\Module\Provider\ProviderRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ProviderRegistry::class)]
final class ProviderRegistryTest extends TestCase
{
    private ProviderRegistry $registry;
    private ContainerInterface $container;

    protected function setUp(): void
    {
        $this->registry = new ProviderRegistry();
        $this->container = $this->createMock(ContainerInterface::class);
    }

    #[Test]
    public function it_registers_class_providers(): void
    {
        $this->container->expects(self::once())
            ->method('bind')
            ->with('App\\UserService', 'App\\UserService');

        $this->registry->registerProviders(
            module: 'App\\UserModule',
            providerClasses: ['App\\UserService'],
            container: $this->container,
        );

        self::assertSame(
            ['App\\UserService'],
            $this->registry->getProvidersForModule('App\\UserModule'),
        );
    }

    #[Test]
    public function it_registers_factory_bindings(): void
    {
        $factory = fn () => new \stdClass();

        $this->container->expects(self::once())
            ->method('bind')
            ->with('App\\SomeInterface', $factory);

        $this->registry->registerFactory(
            module: 'App\\SomeModule',
            abstract: 'App\\SomeInterface',
            factory: $factory,
            container: $this->container,
        );

        self::assertContains(
            'App\\SomeInterface',
            $this->registry->getProvidersForModule('App\\SomeModule'),
        );
    }

    #[Test]
    public function it_registers_value_providers(): void
    {
        $value = ['host' => 'localhost', 'port' => 5432];

        $this->container->expects(self::once())
            ->method('instance')
            ->with('database.config', $value);

        $this->registry->registerValue(
            module: 'App\\DatabaseModule',
            abstract: 'database.config',
            value: $value,
            container: $this->container,
        );

        self::assertContains(
            'database.config',
            $this->registry->getProvidersForModule('App\\DatabaseModule'),
        );
    }

    #[Test]
    public function it_registers_alias_bindings(): void
    {
        $this->container->expects(self::once())
            ->method('bind')
            ->with('App\\LoggerInterface', 'App\\FileLogger');

        $this->registry->registerAlias(
            module: 'App\\LogModule',
            abstract: 'App\\LoggerInterface',
            concrete: 'App\\FileLogger',
            container: $this->container,
        );

        self::assertContains(
            'App\\LoggerInterface',
            $this->registry->getProvidersForModule('App\\LogModule'),
        );
    }

    #[Test]
    public function it_tracks_exported_providers(): void
    {
        $this->container->expects(self::once())
            ->method('bind');

        $this->registry->registerProviders(
            module: 'App\\UserModule',
            providerClasses: ['App\\UserService'],
            container: $this->container,
        );

        $this->registry->markExported('App\\UserModule', ['App\\UserService']);

        self::assertSame(
            ['App\\UserService'],
            $this->registry->getExportedProviders('App\\UserModule'),
        );
    }

    #[Test]
    public function it_only_exposes_exported_providers_cross_module(): void
    {
        $this->container->method('bind');

        $this->registry->registerProviders(
            module: 'App\\UserModule',
            providerClasses: ['App\\UserService', 'App\\InternalHelper'],
            container: $this->container,
        );

        $this->registry->markExported('App\\UserModule', ['App\\UserService']);

        // Only UserService is visible cross-module
        $exported = $this->registry->getExportedProviders('App\\UserModule');
        self::assertContains('App\\UserService', $exported);
        self::assertNotContains('App\\InternalHelper', $exported);
    }

    #[Test]
    public function it_returns_empty_array_for_unknown_module(): void
    {
        self::assertSame([], $this->registry->getProvidersForModule('App\\Unknown'));
    }

    #[Test]
    public function it_returns_empty_exports_for_unknown_module(): void
    {
        self::assertSame([], $this->registry->getExportedProviders('App\\Unknown'));
    }
}
