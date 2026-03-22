<?php

declare(strict_types=1);

namespace Lattice\Routing\Tests\Unit;

use Lattice\Routing\Attributes\Get;
use Lattice\Routing\Attributes\Post;
use Lattice\Routing\Attributes\Put;
use Lattice\Routing\Attributes\Patch;
use Lattice\Routing\Attributes\Delete;
use Lattice\Routing\Attributes\Head;
use Lattice\Routing\Attributes\Options;
use Lattice\Routing\Tests\Fixtures\UserController;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class HttpMethodAttributeTest extends TestCase
{
    public function testGetAttributeOnIndexMethod(): void
    {
        $method = new ReflectionMethod(UserController::class, 'index');
        $attrs = $method->getAttributes(Get::class);

        $this->assertCount(1, $attrs);
        $get = $attrs[0]->newInstance();
        $this->assertSame('', $get->path);
    }

    public function testGetAttributeWithPath(): void
    {
        $method = new ReflectionMethod(UserController::class, 'show');
        $attrs = $method->getAttributes(Get::class);

        $this->assertCount(1, $attrs);
        $get = $attrs[0]->newInstance();
        $this->assertSame('/{id}', $get->path);
    }

    public function testPostAttribute(): void
    {
        $method = new ReflectionMethod(UserController::class, 'create');
        $attrs = $method->getAttributes(Post::class);

        $this->assertCount(1, $attrs);
        $post = $attrs[0]->newInstance();
        $this->assertSame('', $post->path);
    }

    public function testPutAttribute(): void
    {
        $method = new ReflectionMethod(UserController::class, 'update');
        $attrs = $method->getAttributes(Put::class);

        $this->assertCount(1, $attrs);
        $put = $attrs[0]->newInstance();
        $this->assertSame('/{id}', $put->path);
    }

    public function testDeleteAttribute(): void
    {
        $method = new ReflectionMethod(UserController::class, 'destroy');
        $attrs = $method->getAttributes(Delete::class);

        $this->assertCount(1, $attrs);
        $delete = $attrs[0]->newInstance();
        $this->assertSame('/{id}', $delete->path);
    }

    public function testAllHttpMethodAttributesTargetMethod(): void
    {
        $classes = [Get::class, Post::class, Put::class, Patch::class, Delete::class, Head::class, Options::class];

        foreach ($classes as $class) {
            $reflection = new \ReflectionClass($class);
            $attributes = $reflection->getAttributes(\Attribute::class);
            $this->assertCount(1, $attributes, "$class should have Attribute");
            $attr = $attributes[0]->newInstance();
            $this->assertSame(\Attribute::TARGET_METHOD, $attr->flags, "$class should target METHOD");
        }
    }

    public function testAllHttpMethodAttributesHavePathProperty(): void
    {
        $classes = [
            Get::class, Post::class, Put::class, Patch::class,
            Delete::class, Head::class, Options::class,
        ];

        foreach ($classes as $class) {
            $instance = new $class('/test');
            $this->assertSame('/test', $instance->path, "$class should store path");
        }
    }
}
