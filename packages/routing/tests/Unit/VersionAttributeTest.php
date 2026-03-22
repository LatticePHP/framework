<?php

declare(strict_types=1);

namespace Lattice\Routing\Tests\Unit;

use Lattice\Routing\Attributes\Version;
use Lattice\Routing\Tests\Fixtures\UserController;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class VersionAttributeTest extends TestCase
{
    public function testVersionAttributeOnClass(): void
    {
        $reflection = new ReflectionClass(UserController::class);
        $attrs = $reflection->getAttributes(Version::class);

        $this->assertCount(1, $attrs);
        $version = $attrs[0]->newInstance();
        $this->assertSame('v1', $version->versions);
    }

    public function testVersionWithMultipleVersions(): void
    {
        $version = new Version(['v1', 'v2']);
        $this->assertSame(['v1', 'v2'], $version->versions);
    }

    public function testVersionWithSingleString(): void
    {
        $version = new Version('v1');
        $this->assertSame('v1', $version->versions);
    }

    public function testVersionTargetsClassAndMethod(): void
    {
        $reflection = new ReflectionClass(Version::class);
        $attributes = $reflection->getAttributes(\Attribute::class);

        $this->assertCount(1, $attributes);
        $attr = $attributes[0]->newInstance();
        $this->assertSame(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD, $attr->flags);
    }
}
