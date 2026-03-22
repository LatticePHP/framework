<?php

declare(strict_types=1);

namespace Lattice\Observability;

use Lattice\Contracts\Observability\CorrelationContextInterface;

final class CorrelationContext implements CorrelationContextInterface
{
    public function __construct(
        private readonly string $correlationId,
        private readonly ?string $traceId = null,
        private readonly ?string $spanId = null,
        private readonly ?string $tenantId = null,
    ) {}

    public function getCorrelationId(): string
    {
        return $this->correlationId;
    }

    public function getTraceId(): ?string
    {
        return $this->traceId;
    }

    public function getSpanId(): ?string
    {
        return $this->spanId;
    }

    public function getTenantId(): ?string
    {
        return $this->tenantId;
    }

    public function toArray(): array
    {
        return array_filter([
            'correlationId' => $this->correlationId,
            'traceId' => $this->traceId,
            'spanId' => $this->spanId,
            'tenantId' => $this->tenantId,
        ], static fn (mixed $value): bool => $value !== null);
    }

    public static function fromHeaders(array $headers): self
    {
        $normalize = static function (string $key) use ($headers): ?string {
            // Check exact key first, then case-insensitive
            if (isset($headers[$key])) {
                return $headers[$key];
            }
            $lower = strtolower($key);
            foreach ($headers as $k => $v) {
                if (strtolower((string) $k) === $lower) {
                    return $v;
                }
            }
            return null;
        };

        return new self(
            correlationId: $normalize('X-Correlation-ID') ?? self::generateUuid(),
            traceId: $normalize('X-Trace-ID'),
            spanId: $normalize('X-Span-ID'),
            tenantId: $normalize('X-Tenant-ID'),
        );
    }

    public static function generate(): self
    {
        return new self(
            correlationId: self::generateUuid(),
        );
    }

    private static function generateUuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr(ord($bytes[6]) & 0x0f | 0x40); // version 4
        $bytes[8] = chr(ord($bytes[8]) & 0x3f | 0x80); // variant 1

        return sprintf(
            '%s-%s-%s-%s-%s',
            bin2hex(substr($bytes, 0, 4)),
            bin2hex(substr($bytes, 4, 2)),
            bin2hex(substr($bytes, 6, 2)),
            bin2hex(substr($bytes, 8, 2)),
            bin2hex(substr($bytes, 10, 6)),
        );
    }
}
