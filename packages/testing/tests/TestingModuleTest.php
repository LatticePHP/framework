<?php

declare(strict_types=1);

namespace Lattice\Testing\Tests;

use Lattice\Testing\TestingModule;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TestingModuleTest extends TestCase
{
    #[Test]
    public function it_creates_from_module_class(): void
    {
        $module = TestingModule::create('App\\AppModule');

        $this->assertInstanceOf(TestingModule::class, $module);
    }

    #[Test]
    public function it_overrides_providers(): void
    {
        $module = TestingModule::create('App\\AppModule')
            ->overrideProvider('App\\UserService', 'App\\MockUserService');

        $overrides = $module->getOverrides();

        $this->assertArrayHasKey('App\\UserService', $overrides);
        $this->assertSame('App\\MockUserService', $overrides['App\\UserService']);
    }

    #[Test]
    public function it_overrides_multiple_providers(): void
    {
        $module = TestingModule::create('App\\AppModule')
            ->overrideProvider('App\\UserService', 'App\\MockUserService')
            ->overrideProvider('App\\PaymentGateway', 'App\\FakePaymentGateway');

        $overrides = $module->getOverrides();

        $this->assertCount(2, $overrides);
        $this->assertSame('App\\MockUserService', $overrides['App\\UserService']);
        $this->assertSame('App\\FakePaymentGateway', $overrides['App\\PaymentGateway']);
    }

    #[Test]
    public function it_compiles_and_returns_container(): void
    {
        $module = TestingModule::create('App\\AppModule')
            ->overrideProvider('App\\UserService', 'App\\MockUserService');

        $container = $module->compile();

        $this->assertInstanceOf(\Lattice\Testing\TestContainer::class, $container);
    }

    #[Test]
    public function it_resolves_overridden_providers_from_container(): void
    {
        $module = TestingModule::create('App\\AppModule')
            ->overrideProvider('original', 'replacement');

        $container = $module->compile();

        // The container should have the override registered
        $this->assertTrue($container->has('original'));
        $this->assertSame('replacement', $container->get('original'));
    }

    #[Test]
    public function it_returns_self_for_fluent_chaining(): void
    {
        $module = TestingModule::create('App\\AppModule');
        $result = $module->overrideProvider('App\\Foo', 'App\\Bar');

        $this->assertSame($module, $result);
    }
}
