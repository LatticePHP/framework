<?php

declare(strict_types=1);

namespace Lattice\Compiler\Tests\Unit;

use Lattice\Compiler\Discovery\AttributeMetadata;
use Lattice\Compiler\Discovery\ControllerMetadata;
use Lattice\Compiler\Discovery\ModuleMetadata;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MetadataTest extends TestCase
{
    #[Test]
    public function test_attribute_metadata_default_values(): void
    {
        $meta = new AttributeMetadata(className: 'App\\SomeClass');

        self::assertSame('App\\SomeClass', $meta->className);
        self::assertFalse($meta->isModule);
        self::assertFalse($meta->isController);
        self::assertFalse($meta->isInjectable);
        self::assertFalse($meta->isGlobal);
        self::assertSame([], $meta->imports);
        self::assertSame([], $meta->providers);
        self::assertSame([], $meta->controllers);
        self::assertSame([], $meta->exports);
        self::assertSame('', $meta->controllerPrefix);
    }

    #[Test]
    public function test_attribute_metadata_with_all_values(): void
    {
        $meta = new AttributeMetadata(
            className: 'App\\UserModule',
            isModule: true,
            isController: false,
            isInjectable: false,
            isGlobal: true,
            imports: ['App\\AuthModule'],
            providers: ['App\\UserService'],
            controllers: ['App\\UserController'],
            exports: ['App\\UserService'],
            controllerPrefix: '/users',
        );

        self::assertSame('App\\UserModule', $meta->className);
        self::assertTrue($meta->isModule);
        self::assertFalse($meta->isController);
        self::assertFalse($meta->isInjectable);
        self::assertTrue($meta->isGlobal);
        self::assertSame(['App\\AuthModule'], $meta->imports);
        self::assertSame(['App\\UserService'], $meta->providers);
        self::assertSame(['App\\UserController'], $meta->controllers);
        self::assertSame(['App\\UserService'], $meta->exports);
        self::assertSame('/users', $meta->controllerPrefix);
    }

    #[Test]
    public function test_controller_metadata_default_prefix(): void
    {
        $meta = new ControllerMetadata(className: 'App\\UserController');

        self::assertSame('App\\UserController', $meta->className);
        self::assertSame('', $meta->prefix);
    }

    #[Test]
    public function test_controller_metadata_with_prefix(): void
    {
        $meta = new ControllerMetadata(className: 'App\\UserController', prefix: '/api/users');

        self::assertSame('App\\UserController', $meta->className);
        self::assertSame('/api/users', $meta->prefix);
    }

    #[Test]
    public function test_module_metadata_default_values(): void
    {
        $meta = new ModuleMetadata();

        self::assertSame([], $meta->imports);
        self::assertSame([], $meta->providers);
        self::assertSame([], $meta->controllers);
        self::assertSame([], $meta->exports);
        self::assertFalse($meta->isGlobal);
    }

    #[Test]
    public function test_module_metadata_with_all_values(): void
    {
        $meta = new ModuleMetadata(
            imports: ['App\\AuthModule'],
            providers: ['App\\UserService'],
            controllers: ['App\\UserController'],
            exports: ['App\\UserService'],
            isGlobal: true,
        );

        self::assertSame(['App\\AuthModule'], $meta->imports);
        self::assertSame(['App\\UserService'], $meta->providers);
        self::assertSame(['App\\UserController'], $meta->controllers);
        self::assertSame(['App\\UserService'], $meta->exports);
        self::assertTrue($meta->isGlobal);
    }
}
