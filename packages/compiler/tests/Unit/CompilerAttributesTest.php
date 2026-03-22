<?php

declare(strict_types=1);

namespace Lattice\Compiler\Tests\Unit;

use Attribute;
use Lattice\Compiler\Attributes\GlobalModule;
use Lattice\Compiler\Attributes\Injectable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class CompilerAttributesTest extends TestCase
{
    #[Test]
    public function test_global_module_is_valid_php_attribute(): void
    {
        $ref = new ReflectionClass(GlobalModule::class);
        $attributes = $ref->getAttributes(Attribute::class);

        self::assertCount(1, $attributes);

        $attr = $attributes[0]->newInstance();
        self::assertSame(Attribute::TARGET_CLASS, $attr->flags);
    }

    #[Test]
    public function test_global_module_can_be_instantiated(): void
    {
        $instance = new GlobalModule();

        self::assertInstanceOf(GlobalModule::class, $instance);
    }

    #[Test]
    public function test_injectable_is_valid_php_attribute(): void
    {
        $ref = new ReflectionClass(Injectable::class);
        $attributes = $ref->getAttributes(Attribute::class);

        self::assertCount(1, $attributes);

        $attr = $attributes[0]->newInstance();
        self::assertSame(Attribute::TARGET_CLASS, $attr->flags);
    }

    #[Test]
    public function test_injectable_can_be_instantiated(): void
    {
        $instance = new Injectable();

        self::assertInstanceOf(Injectable::class, $instance);
    }

    #[Test]
    public function test_global_module_targets_class_only(): void
    {
        $ref = new ReflectionClass(GlobalModule::class);
        $attributes = $ref->getAttributes(Attribute::class);
        $attr = $attributes[0]->newInstance();

        // TARGET_CLASS is 1, TARGET_METHOD is 2, TARGET_PROPERTY is 8, etc.
        // Verify it does NOT target methods or properties
        self::assertSame(0, $attr->flags & Attribute::TARGET_METHOD);
        self::assertSame(0, $attr->flags & Attribute::TARGET_PROPERTY);
    }

    #[Test]
    public function test_injectable_targets_class_only(): void
    {
        $ref = new ReflectionClass(Injectable::class);
        $attributes = $ref->getAttributes(Attribute::class);
        $attr = $attributes[0]->newInstance();

        self::assertSame(0, $attr->flags & Attribute::TARGET_METHOD);
        self::assertSame(0, $attr->flags & Attribute::TARGET_PROPERTY);
    }
}
