<?php

declare(strict_types=1);

namespace Lattice\Routing\Tests\Unit;

use Lattice\Routing\Attributes\Controller;
use Lattice\Routing\Tests\Fixtures\UserController;
use Lattice\Routing\Tests\Fixtures\NoPrefixController;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class ControllerAttributeTest extends TestCase
{
    public function testControllerAttributeWithPrefix(): void
    {
        $reflection = new ReflectionClass(UserController::class);
        $attributes = $reflection->getAttributes(Controller::class);

        $this->assertCount(1, $attributes);

        $controller = $attributes[0]->newInstance();
        $this->assertSame('/users', $controller->prefix);
    }

    public function testControllerAttributeWithoutPrefix(): void
    {
        $reflection = new ReflectionClass(NoPrefixController::class);
        $attributes = $reflection->getAttributes(Controller::class);

        $this->assertCount(1, $attributes);

        $controller = $attributes[0]->newInstance();
        $this->assertSame('', $controller->prefix);
    }

    public function testControllerAttributeTargetsClassOnly(): void
    {
        $reflection = new ReflectionClass(Controller::class);
        $attributes = $reflection->getAttributes(\Attribute::class);

        $this->assertCount(1, $attributes);
        $attr = $attributes[0]->newInstance();
        $this->assertSame(\Attribute::TARGET_CLASS, $attr->flags);
    }
}
