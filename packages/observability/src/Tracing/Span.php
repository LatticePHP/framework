<?php

declare(strict_types=1);

namespace Lattice\Observability\Tracing;

final class Span
{
    /**
     * @param array<string, mixed> $attributes
     */
    public function __construct(
        public readonly string $name,
        public readonly string $traceId,
        public readonly string $spanId,
        public readonly ?string $parentSpanId = null,
        public readonly float $startTime = 0.0,
        public float $endTime = 0.0,
        public array $attributes = [],
        public string $status = 'ok',
    ) {}

    public function finish(?float $endTime = null): void
    {
        $this->endTime = $endTime ?? microtime(true);
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
    }

    public function setAttribute(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
    }

    public function getDuration(): float
    {
        return $this->endTime - $this->startTime;
    }

    public function toArray(): array
    {
        return array_filter([
            'name' => $this->name,
            'traceId' => $this->traceId,
            'spanId' => $this->spanId,
            'parentSpanId' => $this->parentSpanId,
            'startTime' => $this->startTime,
            'endTime' => $this->endTime,
            'duration' => $this->getDuration(),
            'attributes' => $this->attributes ?: null,
            'status' => $this->status,
        ], static fn (mixed $value): bool => $value !== null);
    }
}
