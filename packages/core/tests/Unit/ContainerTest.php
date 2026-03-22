<?php

declare(strict_types=1);

namespace Lattice\Core\Tests\Unit;

use Lattice\Contracts\Container\ContainerInterface;
use Lattice\Core\Container\Container;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\NotFoundExceptionInterface;

final class ContainerTest extends TestCase
{
    private Container $container;

    protected function setUp(): void
    {
        $this->container = new Container();
    }

    #[Test]
    public function it_implements_contract_interface(): void
    {
        $this->assertInstanceOf(ContainerInterface::class, $this->container);
    }

    #[Test]
    public function bind_and_make_with_closure(): void
    {
        $this->container->bind('foo', fn () => 'bar');

        $this->assertSame('bar', $this->container->make('foo'));
    }

    #[Test]
    public function bind_returns_new_instance_each_time(): void
    {
        $this->container->bind(\stdClass::class, fn () => new \stdClass());

        $a = $this->container->make(\stdClass::class);
        $b = $this->container->make(\stdClass::class);

        $this->assertNotSame($a, $b);
    }

    #[Test]
    public function singleton_returns_same_instance(): void
    {
        $this->container->singleton(\stdClass::class, fn () => new \stdClass());

        $a = $this->container->make(\stdClass::class);
        $b = $this->container->make(\stdClass::class);

        $this->assertSame($a, $b);
    }

    #[Test]
    public function instance_stores_existing_object(): void
    {
        $obj = new \stdClass();
        $obj->value = 42;

        $this->container->instance('thing', $obj);

        $this->assertSame($obj, $this->container->make('thing'));
        $this->assertSame(42, $this->container->make('thing')->value);
    }

    #[Test]
    public function has_returns_true_for_bound_abstract(): void
    {
        $this->container->bind('foo', fn () => 'bar');

        $this->assertTrue($this->container->has('foo'));
        $this->assertFalse($this->container->has('missing'));
    }

    #[Test]
    public function get_resolves_like_make(): void
    {
        $this->container->bind('foo', fn () => 'bar');

        $this->assertSame('bar', $this->container->get('foo'));
    }

    #[Test]
    public function get_throws_not_found_for_missing(): void
    {
        $this->expectException(NotFoundExceptionInterface::class);

        $this->container->get('nonexistent');
    }

    #[Test]
    public function make_throws_for_unresolvable(): void
    {
        $this->expectException(\RuntimeException::class);

        $this->container->make('Nonexistent\\Class\\That\\Does\\Not\\Exist');
    }

    #[Test]
    public function auto_wiring_resolves_constructor_dependencies(): void
    {
        $result = $this->container->make(AutoWireTarget::class);

        $this->assertInstanceOf(AutoWireTarget::class, $result);
        $this->assertInstanceOf(AutoWireDependency::class, $result->dep);
    }

    #[Test]
    public function auto_wiring_with_parameters(): void
    {
        $dep = new AutoWireDependency();
        $result = $this->container->make(AutoWireTarget::class, ['dep' => $dep]);

        $this->assertSame($dep, $result->dep);
    }

    #[Test]
    public function auto_wiring_resolves_default_values(): void
    {
        $result = $this->container->make(AutoWireWithDefault::class);

        $this->assertInstanceOf(AutoWireWithDefault::class, $result);
        $this->assertSame('default', $result->value);
    }

    #[Test]
    public function bind_with_class_string_concrete(): void
    {
        $this->container->bind(AutoWireDependency::class, AutoWireDependency::class);

        $result = $this->container->make(AutoWireDependency::class);

        $this->assertInstanceOf(AutoWireDependency::class, $result);
    }

    #[Test]
    public function reset_clears_singleton_instances_but_keeps_bindings(): void
    {
        $this->container->singleton(\stdClass::class, fn () => new \stdClass());

        $a = $this->container->make(\stdClass::class);
        $this->container->reset();
        $b = $this->container->make(\stdClass::class);

        $this->assertNotSame($a, $b);
    }

    #[Test]
    public function reset_clears_instances(): void
    {
        $obj = new \stdClass();
        $this->container->instance('thing', $obj);

        $this->assertTrue($this->container->has('thing'));
        $this->container->reset();
        $this->assertFalse($this->container->has('thing'));
    }
}

// Test helper classes
class AutoWireDependency
{
    public function __construct() {}
}

class AutoWireTarget
{
    public function __construct(public readonly AutoWireDependency $dep) {}
}

class AutoWireWithDefault
{
    public function __construct(
        public readonly AutoWireDependency $dep = new AutoWireDependency(),
        public readonly string $value = 'default',
    ) {}
}
