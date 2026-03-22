<?php

declare(strict_types=1);

namespace Lattice\Observability\Tests\Unit;

use Lattice\Observability\Tracing\Span;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SpanTest extends TestCase
{
    #[Test]
    public function it_creates_span_with_required_fields(): void
    {
        $span = new Span(
            name: 'UserController.getUser',
            traceId: 'trace-123',
            spanId: 'span-456',
        );

        $this->assertSame('UserController.getUser', $span->name);
        $this->assertSame('trace-123', $span->traceId);
        $this->assertSame('span-456', $span->spanId);
        $this->assertNull($span->parentSpanId);
        $this->assertSame('ok', $span->status);
    }

    #[Test]
    public function it_creates_span_with_all_fields(): void
    {
        $span = new Span(
            name: 'UserController.getUser',
            traceId: 'trace-123',
            spanId: 'span-456',
            parentSpanId: 'span-parent',
            startTime: 1000.0,
            endTime: 1001.5,
            attributes: ['key' => 'value'],
            status: 'error',
        );

        $this->assertSame('span-parent', $span->parentSpanId);
        $this->assertSame(1000.0, $span->startTime);
        $this->assertSame(1001.5, $span->endTime);
        $this->assertSame(['key' => 'value'], $span->attributes);
        $this->assertSame('error', $span->status);
    }

    #[Test]
    public function finish_sets_end_time(): void
    {
        $span = new Span(
            name: 'test',
            traceId: 'trace',
            spanId: 'span',
            startTime: microtime(true),
        );

        $before = microtime(true);
        $span->finish();
        $after = microtime(true);

        $this->assertGreaterThanOrEqual($before, $span->endTime);
        $this->assertLessThanOrEqual($after, $span->endTime);
    }

    #[Test]
    public function finish_accepts_custom_end_time(): void
    {
        $span = new Span(name: 'test', traceId: 'trace', spanId: 'span');

        $span->finish(999.99);

        $this->assertSame(999.99, $span->endTime);
    }

    #[Test]
    public function get_duration_returns_difference(): void
    {
        $span = new Span(
            name: 'test',
            traceId: 'trace',
            spanId: 'span',
            startTime: 100.0,
            endTime: 100.5,
        );

        $this->assertEqualsWithDelta(0.5, $span->getDuration(), 0.0001);
    }

    #[Test]
    public function set_attribute_adds_attribute(): void
    {
        $span = new Span(name: 'test', traceId: 'trace', spanId: 'span');

        $span->setAttribute('http.method', 'GET');
        $span->setAttribute('http.status', 200);

        $this->assertSame('GET', $span->attributes['http.method']);
        $this->assertSame(200, $span->attributes['http.status']);
    }

    #[Test]
    public function set_status_changes_status(): void
    {
        $span = new Span(name: 'test', traceId: 'trace', spanId: 'span');

        $this->assertSame('ok', $span->status);

        $span->setStatus('error');

        $this->assertSame('error', $span->status);
    }

    #[Test]
    public function to_array_returns_complete_representation(): void
    {
        $span = new Span(
            name: 'test',
            traceId: 'trace-1',
            spanId: 'span-1',
            parentSpanId: 'span-parent',
            startTime: 100.0,
            endTime: 100.5,
            attributes: ['key' => 'val'],
            status: 'ok',
        );

        $array = $span->toArray();

        $this->assertSame('test', $array['name']);
        $this->assertSame('trace-1', $array['traceId']);
        $this->assertSame('span-1', $array['spanId']);
        $this->assertSame('span-parent', $array['parentSpanId']);
        $this->assertSame(100.0, $array['startTime']);
        $this->assertSame(100.5, $array['endTime']);
        $this->assertEqualsWithDelta(0.5, $array['duration'], 0.0001);
        $this->assertSame(['key' => 'val'], $array['attributes']);
        $this->assertSame('ok', $array['status']);
    }

    #[Test]
    public function to_array_excludes_null_parent_span_id(): void
    {
        $span = new Span(name: 'test', traceId: 'trace', spanId: 'span');

        $array = $span->toArray();

        $this->assertArrayNotHasKey('parentSpanId', $array);
    }
}
