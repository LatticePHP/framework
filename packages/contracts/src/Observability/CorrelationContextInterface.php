<?php

declare(strict_types=1);

namespace Lattice\Contracts\Observability;

interface CorrelationContextInterface
{
    public function getCorrelationId(): string;

    public function getTraceId(): ?string;

    public function getSpanId(): ?string;

    public function getTenantId(): ?string;

    public function toArray(): array;
}
