<?php

declare(strict_types=1);

namespace Lattice\Module\Tests\Unit;

use Lattice\Module\Attributes\OwnedByModule;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class OwnedByModuleAttributeTest extends TestCase
{
    #[Test]
    public function test_attribute_stores_module_name(): void
    {
        $attr = new OwnedByModule('App\Modules\UserModule');

        self::assertSame('App\Modules\UserModule', $attr->module);
    }

    #[Test]
    public function test_attribute_can_be_read_from_class_reflection(): void
    {
        $ref = new \ReflectionClass(OwnedByModuleTestModel::class);
        $attributes = $ref->getAttributes(OwnedByModule::class);

        self::assertCount(1, $attributes);

        $instance = $attributes[0]->newInstance();
        self::assertSame('App\Modules\OrderModule', $instance->module);
    }

    #[Test]
    public function test_attribute_targets_class_only(): void
    {
        $ref = new \ReflectionClass(OwnedByModule::class);
        $attributes = $ref->getAttributes(\Attribute::class);

        self::assertCount(1, $attributes);

        $attrInstance = $attributes[0]->newInstance();
        self::assertSame(\Attribute::TARGET_CLASS, $attrInstance->flags);
    }
}

#[OwnedByModule('App\Modules\OrderModule')]
final class OwnedByModuleTestModel {}
