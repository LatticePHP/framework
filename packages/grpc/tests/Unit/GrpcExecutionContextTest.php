<?php

declare(strict_types=1);

namespace Lattice\Grpc\Tests\Unit;

use Lattice\Contracts\Context\ExecutionType;
use Lattice\Contracts\Context\PrincipalInterface;
use Lattice\Grpc\GrpcExecutionContext;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class GrpcExecutionContextTest extends TestCase
{
    #[Test]
    public function typeIsGrpc(): void
    {
        $context = new GrpcExecutionContext(
            module: 'greeter',
            class: 'GreeterService',
            method: 'SayHello',
            correlationId: 'corr-123',
        );

        $this->assertSame(ExecutionType::Grpc, $context->getType());
    }

    #[Test]
    public function returnsModuleAndHandler(): void
    {
        $context = new GrpcExecutionContext(
            module: 'users',
            class: 'UserService',
            method: 'GetUser',
            correlationId: 'corr-456',
        );

        $this->assertSame('users', $context->getModule());
        $this->assertSame('UserService', $context->getClass());
        $this->assertSame('GetUser', $context->getMethod());
        $this->assertSame('UserService::GetUser', $context->getHandler());
        $this->assertSame('corr-456', $context->getCorrelationId());
    }

    #[Test]
    public function principalIsNullByDefault(): void
    {
        $context = new GrpcExecutionContext(
            module: 'test',
            class: 'TestService',
            method: 'Do',
            correlationId: 'c-1',
        );

        $this->assertNull($context->getPrincipal());
    }

    #[Test]
    public function principalCanBeProvided(): void
    {
        $principal = $this->createStub(PrincipalInterface::class);
        $principal->method('getId')->willReturn('user-1');

        $context = new GrpcExecutionContext(
            module: 'test',
            class: 'TestService',
            method: 'Do',
            correlationId: 'c-1',
            principal: $principal,
        );

        $this->assertSame($principal, $context->getPrincipal());
        $this->assertSame('user-1', $context->getPrincipal()->getId());
    }
}
