<?php

declare(strict_types=1);

namespace Lattice\Observability\Logger;

use Lattice\Observability\CorrelationContext;

final class ContextEnricher implements LogHandlerInterface
{
    private ?CorrelationContext $correlationContext = null;

    /**
     * @param LogHandlerInterface $inner The handler to delegate to after enrichment
     * @param array<string, mixed> $staticContext Static context added to every log entry (e.g., service name, environment)
     */
    public function __construct(
        private readonly LogHandlerInterface $inner,
        private readonly array $staticContext = [],
    ) {}

    /**
     * Set the current correlation context for dynamic enrichment.
     */
    public function setCorrelationContext(?CorrelationContext $context): void
    {
        $this->correlationContext = $context;
    }

    public function handle(LogEntry $entry): void
    {
        $enriched = new LogEntry(
            level: $entry->level,
            message: $entry->message,
            context: array_merge($this->staticContext, $entry->context, $this->gatherDynamicContext()),
            timestamp: $entry->timestamp,
            correlationId: $entry->correlationId,
        );

        $this->inner->handle($enriched);
    }

    /**
     * @return array<string, mixed>
     */
    private function gatherDynamicContext(): array
    {
        $ctx = [];

        if ($this->correlationContext !== null) {
            $correlationId = $this->correlationContext->getCorrelationId();
            if ($correlationId !== '') {
                $ctx['correlation_id'] = $correlationId;
            }

            $traceId = $this->correlationContext->getTraceId();
            if ($traceId !== null) {
                $ctx['trace_id'] = $traceId;
            }

            $spanId = $this->correlationContext->getSpanId();
            if ($spanId !== null) {
                $ctx['span_id'] = $spanId;
            }

            $tenantId = $this->correlationContext->getTenantId();
            if ($tenantId !== null) {
                $ctx['tenant_id'] = $tenantId;
            }
        }

        return $ctx;
    }
}
