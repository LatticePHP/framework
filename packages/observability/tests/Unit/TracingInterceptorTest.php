<?php

declare(strict_types=1);

namespace Lattice\Observability\Tests\Unit;

use Lattice\Contracts\Context\ExecutionContextInterface;
use Lattice\Contracts\Context\ExecutionType;
use Lattice\Observability\Tracing\SpanCollector;
use Lattice\Observability\Tracing\TracingInterceptor;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TracingInterceptorTest extends TestCase
{
    private function createMockContext(
        string $class = 'UserController',
        string $method = 'getUser',
        string $module = 'users',
        string $correlationId = 'corr-test',
    ): ExecutionContextInterface {
        $context = $this->createMock(ExecutionContextInterface::class);
        $context->method('getClass')->willReturn($class);
        $context->method('getMethod')->willReturn($method);
        $context->method('getModule')->willReturn($module);
        $context->method('getCorrelationId')->willReturn($correlationId);
        $context->method('getHandler')->willReturn("{$class}::{$method}");
        $context->method('getType')->willReturn(ExecutionType::Http);
        $context->method('getPrincipal')->willReturn(null);
        return $context;
    }

    #[Test]
    public function it_creates_span_around_handler_execution(): void
    {
        $collector = new SpanCollector();
        $interceptor = new TracingInterceptor($collector);
        $context = $this->createMockContext();

        $interceptor->intercept($context, fn () => 'result');

        $spans = $collector->getSpans();
        $this->assertCount(1, $spans);
        $this->assertSame('UserController.getUser', $spans[0]->name);
    }

    #[Test]
    public function it_returns_handler_result(): void
    {
        $collector = new SpanCollector();
        $interceptor = new TracingInterceptor($collector);
        $context = $this->createMockContext();

        $result = $interceptor->intercept($context, fn () => 'expected-result');

        $this->assertSame('expected-result', $result);
    }

    #[Test]
    public function it_records_duration(): void
    {
        $collector = new SpanCollector();
        $interceptor = new TracingInterceptor($collector);
        $context = $this->createMockContext();

        $interceptor->intercept($context, function () {
            // Simulate some work
            usleep(1000); // 1ms
            return null;
        });

        $span = $collector->getSpans()[0];
        $this->assertGreaterThan(0, $span->getDuration());
        $this->assertGreaterThan($span->startTime, $span->endTime);
    }

    #[Test]
    public function it_sets_ok_status_on_success(): void
    {
        $collector = new SpanCollector();
        $interceptor = new TracingInterceptor($collector);
        $context = $this->createMockContext();

        $interceptor->intercept($context, fn () => 'ok');

        $span = $collector->getSpans()[0];
        $this->assertSame('ok', $span->status);
    }

    #[Test]
    public function it_sets_error_status_on_exception(): void
    {
        $collector = new SpanCollector();
        $interceptor = new TracingInterceptor($collector);
        $context = $this->createMockContext();

        try {
            $interceptor->intercept($context, function () {
                throw new \RuntimeException('Something broke');
            });
        } catch (\RuntimeException) {
            // expected
        }

        $span = $collector->getSpans()[0];
        $this->assertSame('error', $span->status);
        $this->assertSame(\RuntimeException::class, $span->attributes['error.type']);
        $this->assertSame('Something broke', $span->attributes['error.message']);
    }

    #[Test]
    public function it_rethrows_exception(): void
    {
        $collector = new SpanCollector();
        $interceptor = new TracingInterceptor($collector);
        $context = $this->createMockContext();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Something broke');

        $interceptor->intercept($context, function () {
            throw new \RuntimeException('Something broke');
        });
    }

    #[Test]
    public function it_records_handler_attributes(): void
    {
        $collector = new SpanCollector();
        $interceptor = new TracingInterceptor($collector);
        $context = $this->createMockContext();

        $interceptor->intercept($context, fn () => null);

        $span = $collector->getSpans()[0];
        $this->assertSame('UserController', $span->attributes['handler.class']);
        $this->assertSame('getUser', $span->attributes['handler.method']);
        $this->assertSame('users', $span->attributes['module']);
    }

    #[Test]
    public function it_uses_correlation_id_as_trace_id(): void
    {
        $collector = new SpanCollector();
        $interceptor = new TracingInterceptor($collector);
        $context = $this->createMockContext(correlationId: 'my-trace-id');

        $interceptor->intercept($context, fn () => null);

        $span = $collector->getSpans()[0];
        $this->assertSame('my-trace-id', $span->traceId);
    }

    #[Test]
    public function span_is_collected_even_on_error(): void
    {
        $collector = new SpanCollector();
        $interceptor = new TracingInterceptor($collector);
        $context = $this->createMockContext();

        try {
            $interceptor->intercept($context, function () {
                throw new \RuntimeException('fail');
            });
        } catch (\RuntimeException) {
        }

        $this->assertCount(1, $collector->getSpans());
        $this->assertGreaterThan(0, $collector->getSpans()[0]->endTime);
    }
}
