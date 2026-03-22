<?php

declare(strict_types=1);

namespace Lattice\Observability\Tests\Unit;

use Lattice\Contracts\Observability\CorrelationContextInterface;
use Lattice\Observability\CorrelationContext;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CorrelationContextTest extends TestCase
{
    #[Test]
    public function it_implements_correlation_context_interface(): void
    {
        $context = CorrelationContext::generate();
        $this->assertInstanceOf(CorrelationContextInterface::class, $context);
    }

    #[Test]
    public function generate_creates_context_with_uuid_correlation_id(): void
    {
        $context = CorrelationContext::generate();

        $this->assertNotEmpty($context->getCorrelationId());
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $context->getCorrelationId(),
        );
    }

    #[Test]
    public function generate_creates_unique_ids(): void
    {
        $ids = [];
        for ($i = 0; $i < 100; $i++) {
            $ids[] = CorrelationContext::generate()->getCorrelationId();
        }
        $this->assertCount(100, array_unique($ids));
    }

    #[Test]
    public function generate_has_null_optional_fields(): void
    {
        $context = CorrelationContext::generate();

        $this->assertNull($context->getTraceId());
        $this->assertNull($context->getSpanId());
        $this->assertNull($context->getTenantId());
    }

    #[Test]
    public function constructor_accepts_all_fields(): void
    {
        $context = new CorrelationContext(
            correlationId: 'corr-123',
            traceId: 'trace-456',
            spanId: 'span-789',
            tenantId: 'tenant-abc',
        );

        $this->assertSame('corr-123', $context->getCorrelationId());
        $this->assertSame('trace-456', $context->getTraceId());
        $this->assertSame('span-789', $context->getSpanId());
        $this->assertSame('tenant-abc', $context->getTenantId());
    }

    #[Test]
    public function to_array_includes_all_set_fields(): void
    {
        $context = new CorrelationContext(
            correlationId: 'corr-123',
            traceId: 'trace-456',
            spanId: 'span-789',
            tenantId: 'tenant-abc',
        );

        $this->assertSame([
            'correlationId' => 'corr-123',
            'traceId' => 'trace-456',
            'spanId' => 'span-789',
            'tenantId' => 'tenant-abc',
        ], $context->toArray());
    }

    #[Test]
    public function to_array_excludes_null_fields(): void
    {
        $context = new CorrelationContext(correlationId: 'corr-123');

        $array = $context->toArray();

        $this->assertSame(['correlationId' => 'corr-123'], $array);
        $this->assertArrayNotHasKey('traceId', $array);
        $this->assertArrayNotHasKey('spanId', $array);
        $this->assertArrayNotHasKey('tenantId', $array);
    }

    #[Test]
    public function from_headers_extracts_correlation_id(): void
    {
        $context = CorrelationContext::fromHeaders([
            'X-Correlation-ID' => 'my-corr-id',
        ]);

        $this->assertSame('my-corr-id', $context->getCorrelationId());
    }

    #[Test]
    public function from_headers_extracts_all_headers(): void
    {
        $context = CorrelationContext::fromHeaders([
            'X-Correlation-ID' => 'corr-1',
            'X-Trace-ID' => 'trace-1',
            'X-Span-ID' => 'span-1',
            'X-Tenant-ID' => 'tenant-1',
        ]);

        $this->assertSame('corr-1', $context->getCorrelationId());
        $this->assertSame('trace-1', $context->getTraceId());
        $this->assertSame('span-1', $context->getSpanId());
        $this->assertSame('tenant-1', $context->getTenantId());
    }

    #[Test]
    public function from_headers_is_case_insensitive(): void
    {
        $context = CorrelationContext::fromHeaders([
            'x-correlation-id' => 'corr-lower',
            'x-trace-id' => 'trace-lower',
        ]);

        $this->assertSame('corr-lower', $context->getCorrelationId());
        $this->assertSame('trace-lower', $context->getTraceId());
    }

    #[Test]
    public function from_headers_generates_correlation_id_when_absent(): void
    {
        $context = CorrelationContext::fromHeaders([]);

        $this->assertNotEmpty($context->getCorrelationId());
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $context->getCorrelationId(),
        );
    }
}
