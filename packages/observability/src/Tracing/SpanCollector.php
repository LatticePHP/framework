<?php

declare(strict_types=1);

namespace Lattice\Observability\Tracing;

final class SpanCollector
{
    /** @var Span[] */
    private array $spans = [];

    public function add(Span $span): void
    {
        $this->spans[] = $span;
    }

    /**
     * @return Span[]
     */
    public function getSpans(): array
    {
        return $this->spans;
    }

    /**
     * Returns all collected spans and clears the internal storage.
     *
     * @return Span[]
     */
    public function flush(): array
    {
        $spans = $this->spans;
        $this->spans = [];
        return $spans;
    }
}
