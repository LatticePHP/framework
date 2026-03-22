<?php

declare(strict_types=1);

namespace Lattice\Contracts\Tests\Unit\Context;

use Lattice\Contracts\Context\ExecutionContextInterface;
use Lattice\Contracts\Context\ExecutionType;
use Lattice\Contracts\Context\PrincipalInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionEnum;

final class ContextContractsTest extends TestCase
{
    #[Test]
    public function executionContextInterfaceExists(): void
    {
        $this->assertTrue(interface_exists(ExecutionContextInterface::class));
    }

    #[Test]
    public function executionContextInterfaceHasExpectedMethods(): void
    {
        $reflection = new ReflectionClass(ExecutionContextInterface::class);

        $expectedMethods = [
            'getType' => ExecutionType::class,
            'getModule' => 'string',
            'getHandler' => 'string',
            'getClass' => 'string',
            'getMethod' => 'string',
            'getCorrelationId' => 'string',
        ];

        foreach ($expectedMethods as $methodName => $returnType) {
            $this->assertTrue($reflection->hasMethod($methodName), "Missing method: $methodName");
            $this->assertSame(
                $returnType,
                $reflection->getMethod($methodName)->getReturnType()->getName(),
                "Wrong return type for $methodName"
            );
        }

        // getPrincipal returns nullable PrincipalInterface
        $this->assertTrue($reflection->hasMethod('getPrincipal'));
        $principalReturn = $reflection->getMethod('getPrincipal')->getReturnType();
        $this->assertTrue($principalReturn->allowsNull());
        $this->assertSame(PrincipalInterface::class, $principalReturn->getName());
    }

    #[Test]
    public function executionTypeEnumExists(): void
    {
        $this->assertTrue(enum_exists(ExecutionType::class));
    }

    #[Test]
    public function executionTypeEnumIsStringBacked(): void
    {
        $reflection = new ReflectionEnum(ExecutionType::class);
        $this->assertTrue($reflection->isBacked());
        $this->assertSame('string', $reflection->getBackingType()->getName());
    }

    #[Test]
    public function executionTypeEnumHasExpectedCases(): void
    {
        $expectedCases = [
            'Http' => 'http',
            'Grpc' => 'grpc',
            'Message' => 'message',
            'Workflow' => 'workflow',
            'Job' => 'job',
        ];

        $cases = ExecutionType::cases();
        $this->assertCount(count($expectedCases), $cases);

        foreach ($expectedCases as $name => $value) {
            $case = ExecutionType::from($value);
            $this->assertSame($name, $case->name);
            $this->assertSame($value, $case->value);
        }
    }

    #[Test]
    public function principalInterfaceExists(): void
    {
        $this->assertTrue(interface_exists(PrincipalInterface::class));
    }

    #[Test]
    public function principalInterfaceHasExpectedMethods(): void
    {
        $reflection = new ReflectionClass(PrincipalInterface::class);

        $this->assertTrue($reflection->hasMethod('getId'));
        $this->assertTrue($reflection->hasMethod('getType'));
        $this->assertTrue($reflection->hasMethod('getScopes'));
        $this->assertTrue($reflection->hasMethod('getRoles'));
        $this->assertTrue($reflection->hasMethod('hasScope'));
        $this->assertTrue($reflection->hasMethod('hasRole'));

        // getId returns string|int union type
        $idReturn = $reflection->getMethod('getId')->getReturnType();
        $this->assertInstanceOf(\ReflectionUnionType::class, $idReturn);

        $this->assertSame('string', $reflection->getMethod('getType')->getReturnType()->getName());
        $this->assertSame('array', $reflection->getMethod('getScopes')->getReturnType()->getName());
        $this->assertSame('array', $reflection->getMethod('getRoles')->getReturnType()->getName());
        $this->assertSame('bool', $reflection->getMethod('hasScope')->getReturnType()->getName());
        $this->assertSame('bool', $reflection->getMethod('hasRole')->getReturnType()->getName());
    }

    #[Test]
    public function principalCanBeImplemented(): void
    {
        $principal = new class implements PrincipalInterface {
            public function getId(): string|int { return 42; }
            public function getType(): string { return 'user'; }
            public function getScopes(): array { return ['read', 'write']; }
            public function getRoles(): array { return ['admin']; }
            public function hasScope(string $scope): bool { return in_array($scope, $this->getScopes(), true); }
            public function hasRole(string $role): bool { return in_array($role, $this->getRoles(), true); }
        };

        $this->assertSame(42, $principal->getId());
        $this->assertSame('user', $principal->getType());
        $this->assertSame(['read', 'write'], $principal->getScopes());
        $this->assertTrue($principal->hasScope('read'));
        $this->assertFalse($principal->hasScope('delete'));
        $this->assertTrue($principal->hasRole('admin'));
        $this->assertFalse($principal->hasRole('guest'));
    }
}
