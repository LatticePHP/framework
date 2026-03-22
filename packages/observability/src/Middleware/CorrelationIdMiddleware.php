<?php

declare(strict_types=1);

namespace Lattice\Observability\Middleware;

use Lattice\Observability\CorrelationContext;

final class CorrelationIdMiddleware
{
    private ?CorrelationContext $context = null;

    public function process(array $headers, callable $next): mixed
    {
        $correlationId = null;

        // Look for X-Correlation-ID header (case-insensitive)
        foreach ($headers as $key => $value) {
            if (strtolower((string) $key) === 'x-correlation-id') {
                $correlationId = $value;
                break;
            }
        }

        $this->context = $correlationId !== null
            ? new CorrelationContext($correlationId)
            : CorrelationContext::generate();

        return $next($this->context);
    }

    public function getContext(): ?CorrelationContext
    {
        return $this->context;
    }
}
