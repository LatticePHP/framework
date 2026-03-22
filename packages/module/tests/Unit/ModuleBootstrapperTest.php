<?php

declare(strict_types=1);

namespace Lattice\Module\Tests\Unit;

use Lattice\Contracts\Container\ContainerInterface;
use Lattice\Contracts\Module\ModuleLifecycleInterface;
use Lattice\Module\ModuleBootstrapper;
use Lattice\Module\ModuleDefinition;
use Lattice\Module\ModuleRegistry;
use Lattice\Module\Provider\ProviderRegistry;
use Lattice\Module\Exception\UnsatisfiedImportException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ModuleBootstrapper::class)]
final class ModuleBootstrapperTest extends TestCase
{
    private ContainerInterface $container;

    protected function setUp(): void
    {
        $this->container = $this->createMock(ContainerInterface::class);
    }

    #[Test]
    public function it_boots_modules_in_topological_order(): void
    {
        $registry = new ModuleRegistry();
        $registry->register('App\\CoreModule', new ModuleDefinition(
            imports: [],
            providers: ['App\\CoreService'],
            exports: ['App\\CoreService'],
        ));
        $registry->register('App\\UserModule', new ModuleDefinition(
            imports: ['App\\CoreModule'],
            providers: ['App\\UserService'],
            exports: ['App\\UserService'],
        ));

        $this->container->method('bind');
        $this->container->method('has')->willReturn(false);
        $this->container->method('make')->willReturn(null);

        $providerRegistry = new ProviderRegistry();
        $bootstrapper = new ModuleBootstrapper($providerRegistry);

        $bootOrder = $bootstrapper->boot($registry, $this->container);

        self::assertSame(['App\\CoreModule', 'App\\UserModule'], $bootOrder);
    }

    #[Test]
    public function it_registers_providers_during_boot(): void
    {
        $registry = new ModuleRegistry();
        $registry->register('App\\CoreModule', new ModuleDefinition(
            providers: ['App\\CoreService'],
        ));

        $this->container->expects(self::atLeastOnce())
            ->method('bind');
        $this->container->method('has')->willReturn(false);
        $this->container->method('make')->willReturn(null);

        $providerRegistry = new ProviderRegistry();
        $bootstrapper = new ModuleBootstrapper($providerRegistry);
        $bootstrapper->boot($registry, $this->container);

        self::assertSame(
            ['App\\CoreService'],
            $providerRegistry->getProvidersForModule('App\\CoreModule'),
        );
    }

    #[Test]
    public function it_calls_lifecycle_on_module_init(): void
    {
        $lifecycleModule = new class implements ModuleLifecycleInterface {
            public bool $initialized = false;
            public bool $destroyed = false;

            public function onModuleInit(): void
            {
                $this->initialized = true;
            }

            public function onModuleDestroy(): void
            {
                $this->destroyed = true;
            }
        };

        $registry = new ModuleRegistry();
        $registry->register($lifecycleModule::class, new ModuleDefinition());

        $this->container->method('bind');
        $this->container->method('has')->willReturn(true);
        $this->container->method('make')
            ->with($lifecycleModule::class)
            ->willReturn($lifecycleModule);

        $providerRegistry = new ProviderRegistry();
        $bootstrapper = new ModuleBootstrapper($providerRegistry);
        $bootstrapper->boot($registry, $this->container);

        self::assertTrue($lifecycleModule->initialized);
    }

    #[Test]
    public function it_calls_lifecycle_on_module_destroy(): void
    {
        $lifecycleModule = new class implements ModuleLifecycleInterface {
            public bool $destroyed = false;

            public function onModuleInit(): void {}

            public function onModuleDestroy(): void
            {
                $this->destroyed = true;
            }
        };

        $registry = new ModuleRegistry();
        $registry->register($lifecycleModule::class, new ModuleDefinition());

        $this->container->method('bind');
        $this->container->method('has')->willReturn(true);
        $this->container->method('make')
            ->with($lifecycleModule::class)
            ->willReturn($lifecycleModule);

        $providerRegistry = new ProviderRegistry();
        $bootstrapper = new ModuleBootstrapper($providerRegistry);
        $bootstrapper->boot($registry, $this->container);
        $bootstrapper->shutdown($registry, $this->container);

        self::assertTrue($lifecycleModule->destroyed);
    }

    #[Test]
    public function it_validates_all_imports_are_satisfied(): void
    {
        $registry = new ModuleRegistry();
        $registry->register('App\\UserModule', new ModuleDefinition(
            imports: ['App\\MissingModule'],
        ));

        $providerRegistry = new ProviderRegistry();
        $bootstrapper = new ModuleBootstrapper($providerRegistry);

        $this->expectException(UnsatisfiedImportException::class);
        $this->expectExceptionMessage('App\\MissingModule');

        $bootstrapper->boot($registry, $this->container);
    }

    #[Test]
    public function it_marks_exports_during_boot(): void
    {
        $registry = new ModuleRegistry();
        $registry->register('App\\CoreModule', new ModuleDefinition(
            providers: ['App\\CoreService', 'App\\InternalHelper'],
            exports: ['App\\CoreService'],
        ));

        $this->container->method('bind');
        $this->container->method('has')->willReturn(false);
        $this->container->method('make')->willReturn(null);

        $providerRegistry = new ProviderRegistry();
        $bootstrapper = new ModuleBootstrapper($providerRegistry);
        $bootstrapper->boot($registry, $this->container);

        $exported = $providerRegistry->getExportedProviders('App\\CoreModule');
        self::assertContains('App\\CoreService', $exported);
        self::assertNotContains('App\\InternalHelper', $exported);
    }
}
