<?php

declare(strict_types=1);

namespace Lattice\Module\Tests\Unit;

use Lattice\Contracts\Module\ModuleDefinitionInterface;
use Lattice\Module\DynamicModuleDefinition;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(DynamicModuleDefinition::class)]
final class DynamicModuleDefinitionTest extends TestCase
{
    #[Test]
    public function it_implements_module_definition_interface(): void
    {
        $definition = new DynamicModuleDefinition(
            providers: [],
        );

        self::assertInstanceOf(ModuleDefinitionInterface::class, $definition);
    }

    #[Test]
    public function it_accepts_providers_array(): void
    {
        $definition = new DynamicModuleDefinition(
            providers: ['App\\PostgresConnection', 'App\\DatabasePool'],
        );

        self::assertSame(['App\\PostgresConnection', 'App\\DatabasePool'], $definition->getProviders());
    }

    #[Test]
    public function it_accepts_all_module_definition_fields(): void
    {
        $definition = new DynamicModuleDefinition(
            imports: ['App\\CoreModule'],
            providers: ['App\\PostgresConnection'],
            controllers: [],
            exports: ['App\\PostgresConnection'],
        );

        self::assertSame(['App\\CoreModule'], $definition->getImports());
        self::assertSame(['App\\PostgresConnection'], $definition->getProviders());
        self::assertSame([], $definition->getControllers());
        self::assertSame(['App\\PostgresConnection'], $definition->getExports());
    }

    #[Test]
    public function it_supports_factory_method_pattern(): void
    {
        // Simulate: DatabaseModule::forPostgres(host: 'localhost')
        $definition = DynamicModuleDefinition::create(
            providers: ['App\\PostgresConnection'],
            exports: ['App\\PostgresConnection'],
        );

        self::assertInstanceOf(ModuleDefinitionInterface::class, $definition);
        self::assertSame(['App\\PostgresConnection'], $definition->getProviders());
        self::assertSame(['App\\PostgresConnection'], $definition->getExports());
    }

    #[Test]
    public function it_accepts_factory_bindings_as_providers(): void
    {
        $factory = fn () => new \stdClass();

        $definition = new DynamicModuleDefinition(
            providers: ['App\\SomeService'],
            factoryBindings: [
                'App\\SomeInterface' => $factory,
            ],
        );

        self::assertSame(['App\\SomeService'], $definition->getProviders());
        self::assertSame(['App\\SomeInterface' => $factory], $definition->getFactoryBindings());
    }

    #[Test]
    public function it_defaults_factory_bindings_to_empty_array(): void
    {
        $definition = new DynamicModuleDefinition(providers: []);

        self::assertSame([], $definition->getFactoryBindings());
    }
}
