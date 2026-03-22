<?php

declare(strict_types=1);

namespace Lattice\Contracts\Tests\Unit\Module;

use Lattice\Contracts\Module\DynamicModuleInterface;
use Lattice\Contracts\Module\ModuleDefinitionInterface;
use Lattice\Contracts\Module\ModuleLifecycleInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

final class ModuleContractsTest extends TestCase
{
    #[Test]
    public function moduleDefinitionInterfaceExists(): void
    {
        $this->assertTrue(interface_exists(ModuleDefinitionInterface::class));
    }

    #[Test]
    public function moduleDefinitionInterfaceHasExpectedMethods(): void
    {
        $reflection = new ReflectionClass(ModuleDefinitionInterface::class);

        $this->assertTrue($reflection->hasMethod('getImports'));
        $this->assertTrue($reflection->hasMethod('getProviders'));
        $this->assertTrue($reflection->hasMethod('getControllers'));
        $this->assertTrue($reflection->hasMethod('getExports'));

        $this->assertSame('array', $reflection->getMethod('getImports')->getReturnType()->getName());
        $this->assertSame('array', $reflection->getMethod('getProviders')->getReturnType()->getName());
        $this->assertSame('array', $reflection->getMethod('getControllers')->getReturnType()->getName());
        $this->assertSame('array', $reflection->getMethod('getExports')->getReturnType()->getName());
    }

    #[Test]
    public function moduleDefinitionCanBeImplemented(): void
    {
        $mock = new class implements ModuleDefinitionInterface {
            public function getImports(): array { return []; }
            public function getProviders(): array { return ['SomeProvider']; }
            public function getControllers(): array { return ['SomeController']; }
            public function getExports(): array { return ['SomeExport']; }
        };

        $this->assertSame([], $mock->getImports());
        $this->assertSame(['SomeProvider'], $mock->getProviders());
        $this->assertSame(['SomeController'], $mock->getControllers());
        $this->assertSame(['SomeExport'], $mock->getExports());
    }

    #[Test]
    public function dynamicModuleInterfaceExists(): void
    {
        $this->assertTrue(interface_exists(DynamicModuleInterface::class));
    }

    #[Test]
    public function dynamicModuleInterfaceHasRegisterMethod(): void
    {
        $reflection = new ReflectionClass(DynamicModuleInterface::class);

        $this->assertTrue($reflection->hasMethod('register'));

        $method = $reflection->getMethod('register');
        $this->assertTrue($method->isStatic());
        $this->assertSame(ModuleDefinitionInterface::class, $method->getReturnType()->getName());
    }

    #[Test]
    public function moduleLifecycleInterfaceExists(): void
    {
        $this->assertTrue(interface_exists(ModuleLifecycleInterface::class));
    }

    #[Test]
    public function moduleLifecycleInterfaceHasExpectedMethods(): void
    {
        $reflection = new ReflectionClass(ModuleLifecycleInterface::class);

        $this->assertTrue($reflection->hasMethod('onModuleInit'));
        $this->assertTrue($reflection->hasMethod('onModuleDestroy'));

        $this->assertSame('void', $reflection->getMethod('onModuleInit')->getReturnType()->getName());
        $this->assertSame('void', $reflection->getMethod('onModuleDestroy')->getReturnType()->getName());
    }

    #[Test]
    public function moduleLifecycleCanBeImplemented(): void
    {
        $initialized = false;
        $destroyed = false;

        $mock = new class ($initialized, $destroyed) implements ModuleLifecycleInterface {
            public function __construct(private bool &$initialized, private bool &$destroyed) {}
            public function onModuleInit(): void { $this->initialized = true; }
            public function onModuleDestroy(): void { $this->destroyed = true; }
        };

        $mock->onModuleInit();
        $this->assertTrue($initialized);

        $mock->onModuleDestroy();
        $this->assertTrue($destroyed);
    }
}
