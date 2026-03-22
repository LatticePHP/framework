<?php

declare(strict_types=1);

namespace Lattice\Contracts\Tests\Unit\Container;

use Lattice\Contracts\Container\ContainerInterface;
use Psr\Container\ContainerInterface as PsrContainerInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class ContainerContractsTest extends TestCase
{
    #[Test]
    public function containerInterfaceExists(): void
    {
        $this->assertTrue(interface_exists(ContainerInterface::class));
    }

    #[Test]
    public function containerInterfaceExtendsPsrContainer(): void
    {
        $reflection = new ReflectionClass(ContainerInterface::class);
        $this->assertTrue($reflection->isSubclassOf(PsrContainerInterface::class));
    }

    #[Test]
    public function containerInterfaceHasExpectedMethods(): void
    {
        $reflection = new ReflectionClass(ContainerInterface::class);

        $expectedMethods = [
            'bind' => 'void',
            'singleton' => 'void',
            'instance' => 'void',
            'make' => 'mixed',
            'reset' => 'void',
        ];

        foreach ($expectedMethods as $methodName => $returnType) {
            $this->assertTrue($reflection->hasMethod($methodName), "Missing method: $methodName");
            $this->assertSame(
                $returnType,
                $reflection->getMethod($methodName)->getReturnType()->getName(),
                "Wrong return type for $methodName"
            );
        }
    }

    #[Test]
    public function containerMakeHasParametersArgument(): void
    {
        $reflection = new ReflectionClass(ContainerInterface::class);
        $makeParams = $reflection->getMethod('make')->getParameters();

        $this->assertCount(2, $makeParams);
        $this->assertSame('abstract', $makeParams[0]->getName());
        $this->assertSame('parameters', $makeParams[1]->getName());
        $this->assertTrue($makeParams[1]->isDefaultValueAvailable());
        $this->assertSame([], $makeParams[1]->getDefaultValue());
    }
}
