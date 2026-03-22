<?php

declare(strict_types=1);

namespace Lattice\Observability\Tracing;

use Lattice\Contracts\Context\ExecutionContextInterface;
use Lattice\Contracts\Pipeline\InterceptorInterface;

final class TracingInterceptor implements InterceptorInterface
{
    public function __construct(
        private readonly SpanCollector $collector,
    ) {}

    public function intercept(ExecutionContextInterface $context, callable $next): mixed
    {
        $traceId = $context->getCorrelationId();
        $spanId = bin2hex(random_bytes(8));

        $span = new Span(
            name: sprintf('%s.%s', $context->getClass(), $context->getMethod()),
            traceId: $traceId,
            spanId: $spanId,
            startTime: microtime(true),
        );

        try {
            $result = $next($context);
            $span->setStatus('ok');
            return $result;
        } catch (\Throwable $e) {
            $span->setStatus('error');
            $span->setAttribute('error.type', $e::class);
            $span->setAttribute('error.message', $e->getMessage());
            throw $e;
        } finally {
            $span->finish();
            $span->setAttribute('handler.class', $context->getClass());
            $span->setAttribute('handler.method', $context->getMethod());
            $span->setAttribute('module', $context->getModule());
            $this->collector->add($span);
        }
    }
}
