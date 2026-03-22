<?php

declare(strict_types=1);

namespace Lattice\Contracts\Tests\Unit\Observability;

use Lattice\Contracts\Observability\CorrelationContextInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class ObservabilityContractsTest extends TestCase
{
    #[Test]
    public function correlationContextInterfaceExists(): void
    {
        $this->assertTrue(interface_exists(CorrelationContextInterface::class));
    }

    #[Test]
    public function correlationContextInterfaceHasExpectedMethods(): void
    {
        $reflection = new ReflectionClass(CorrelationContextInterface::class);

        $this->assertTrue($reflection->hasMethod('getCorrelationId'));
        $this->assertSame('string', $reflection->getMethod('getCorrelationId')->getReturnType()->getName());

        $this->assertTrue($reflection->hasMethod('toArray'));
        $this->assertSame('array', $reflection->getMethod('toArray')->getReturnType()->getName());

        // Nullable returns
        foreach (['getTraceId', 'getSpanId', 'getTenantId'] as $methodName) {
            $this->assertTrue($reflection->hasMethod($methodName), "Missing method: $methodName");
            $returnType = $reflection->getMethod($methodName)->getReturnType();
            $this->assertTrue($returnType->allowsNull(), "$methodName should be nullable");
            $this->assertSame('string', $returnType->getName(), "$methodName should return ?string");
        }
    }

    #[Test]
    public function correlationContextCanBeImplemented(): void
    {
        $ctx = new class implements CorrelationContextInterface {
            public function getCorrelationId(): string { return 'corr-123'; }
            public function getTraceId(): ?string { return 'trace-abc'; }
            public function getSpanId(): ?string { return null; }
            public function getTenantId(): ?string { return 'tenant-1'; }
            public function toArray(): array {
                return [
                    'correlationId' => $this->getCorrelationId(),
                    'traceId' => $this->getTraceId(),
                    'tenantId' => $this->getTenantId(),
                ];
            }
        };

        $this->assertSame('corr-123', $ctx->getCorrelationId());
        $this->assertSame('trace-abc', $ctx->getTraceId());
        $this->assertNull($ctx->getSpanId());
        $this->assertSame('tenant-1', $ctx->getTenantId());
        $this->assertArrayHasKey('correlationId', $ctx->toArray());
    }
}
