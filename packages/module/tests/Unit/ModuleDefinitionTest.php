<?php

declare(strict_types=1);

namespace Lattice\Module\Tests\Unit;

use Lattice\Contracts\Module\ModuleDefinitionInterface;
use Lattice\Module\Attribute\Module;
use Lattice\Module\ModuleDefinition;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ModuleDefinition::class)]
final class ModuleDefinitionTest extends TestCase
{
    #[Test]
    public function it_implements_module_definition_interface(): void
    {
        $definition = new ModuleDefinition(
            imports: [],
            providers: [],
            controllers: [],
            exports: [],
        );

        self::assertInstanceOf(ModuleDefinitionInterface::class, $definition);
    }

    #[Test]
    public function it_returns_imports(): void
    {
        $definition = new ModuleDefinition(
            imports: ['App\\CoreModule', 'App\\AuthModule'],
            providers: [],
            controllers: [],
            exports: [],
        );

        self::assertSame(['App\\CoreModule', 'App\\AuthModule'], $definition->getImports());
    }

    #[Test]
    public function it_returns_providers(): void
    {
        $definition = new ModuleDefinition(
            imports: [],
            providers: ['App\\UserService', 'App\\LoggerService'],
            controllers: [],
            exports: [],
        );

        self::assertSame(['App\\UserService', 'App\\LoggerService'], $definition->getProviders());
    }

    #[Test]
    public function it_returns_controllers(): void
    {
        $definition = new ModuleDefinition(
            imports: [],
            providers: [],
            controllers: ['App\\UserController'],
            exports: [],
        );

        self::assertSame(['App\\UserController'], $definition->getControllers());
    }

    #[Test]
    public function it_returns_exports(): void
    {
        $definition = new ModuleDefinition(
            imports: [],
            providers: [],
            controllers: [],
            exports: ['App\\UserService'],
        );

        self::assertSame(['App\\UserService'], $definition->getExports());
    }

    #[Test]
    public function it_creates_from_attribute(): void
    {
        $attribute = new Module(
            imports: ['App\\CoreModule'],
            providers: ['App\\UserService'],
            controllers: ['App\\UserController'],
            exports: ['App\\UserService'],
        );

        $definition = ModuleDefinition::fromAttribute($attribute);

        self::assertInstanceOf(ModuleDefinitionInterface::class, $definition);
        self::assertSame(['App\\CoreModule'], $definition->getImports());
        self::assertSame(['App\\UserService'], $definition->getProviders());
        self::assertSame(['App\\UserController'], $definition->getControllers());
        self::assertSame(['App\\UserService'], $definition->getExports());
    }

    #[Test]
    public function it_defaults_to_empty_arrays(): void
    {
        $definition = new ModuleDefinition();

        self::assertSame([], $definition->getImports());
        self::assertSame([], $definition->getProviders());
        self::assertSame([], $definition->getControllers());
        self::assertSame([], $definition->getExports());
    }
}
