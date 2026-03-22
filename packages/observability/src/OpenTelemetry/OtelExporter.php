<?php

declare(strict_types=1);

namespace Lattice\Observability\OpenTelemetry;

use Lattice\Observability\Logger\LogEntry;
use Lattice\Observability\Tracing\Span;

final class OtelExporter
{
    public function __construct(
        private readonly string $endpoint,
        private readonly string $serviceName,
        private readonly float $sampleRate = 1.0,
        private readonly int $timeoutMs = 5000,
    ) {}

    /**
     * Export a single span as an OTLP trace.
     */
    public function exportSpan(Span $span): void
    {
        if (!$this->shouldSample()) {
            return;
        }

        $payload = [
            'resourceSpans' => [[
                'resource' => [
                    'attributes' => [
                        ['key' => 'service.name', 'value' => ['stringValue' => $this->serviceName]],
                    ],
                ],
                'scopeSpans' => [[
                    'scope' => [
                        'name' => 'lattice-observability',
                        'version' => '1.0.0',
                    ],
                    'spans' => [$this->spanToOtlp($span)],
                ]],
            ]],
        ];

        $this->send('/v1/traces', $payload);
    }

    /**
     * Export multiple spans in a single batch.
     *
     * @param Span[] $spans
     */
    public function exportSpans(array $spans): void
    {
        $otlpSpans = [];
        foreach ($spans as $span) {
            if ($this->shouldSample()) {
                $otlpSpans[] = $this->spanToOtlp($span);
            }
        }

        if ($otlpSpans === []) {
            return;
        }

        $payload = [
            'resourceSpans' => [[
                'resource' => [
                    'attributes' => [
                        ['key' => 'service.name', 'value' => ['stringValue' => $this->serviceName]],
                    ],
                ],
                'scopeSpans' => [[
                    'scope' => [
                        'name' => 'lattice-observability',
                        'version' => '1.0.0',
                    ],
                    'spans' => $otlpSpans,
                ]],
            ]],
        ];

        $this->send('/v1/traces', $payload);
    }

    /**
     * Export metrics in OTLP format.
     *
     * @param array<string, mixed> $metrics
     */
    public function exportMetrics(array $metrics): void
    {
        $otlpMetrics = [];

        foreach ($metrics as $name => $metric) {
            $type = $metric['type'] ?? 'gauge';
            $entries = $metric['entries'] ?? [];

            foreach ($entries as $entry) {
                $dataPoint = [
                    'attributes' => $this->labelsToAttributes($entry['labels'] ?? []),
                    'timeUnixNano' => (string) (int) (microtime(true) * 1e9),
                ];

                if ($type === 'histogram') {
                    $dataPoint['count'] = (string) ($entry['count'] ?? 0);
                    $dataPoint['sum'] = $entry['sum'] ?? 0.0;
                } else {
                    $dataPoint['asDouble'] = $entry['value'] ?? 0.0;
                }

                $otlpMetrics[] = [
                    'name' => $name,
                    'description' => '',
                    $type => [
                        'dataPoints' => [$dataPoint],
                    ],
                ];
            }
        }

        if ($otlpMetrics === []) {
            return;
        }

        $payload = [
            'resourceMetrics' => [[
                'resource' => [
                    'attributes' => [
                        ['key' => 'service.name', 'value' => ['stringValue' => $this->serviceName]],
                    ],
                ],
                'scopeMetrics' => [[
                    'scope' => [
                        'name' => 'lattice-observability',
                        'version' => '1.0.0',
                    ],
                    'metrics' => $otlpMetrics,
                ]],
            ]],
        ];

        $this->send('/v1/metrics', $payload);
    }

    /**
     * Export log entries in OTLP format.
     *
     * @param LogEntry[] $entries
     */
    public function exportLogs(array $entries): void
    {
        $otlpLogs = [];

        foreach ($entries as $entry) {
            $otlpLogs[] = [
                'timeUnixNano' => (string) (int) ($entry->timestamp * 1e9),
                'severityText' => strtoupper($entry->level),
                'severityNumber' => $this->logLevelToSeverityNumber($entry->level),
                'body' => ['stringValue' => $entry->message],
                'attributes' => $this->contextToAttributes($entry->context),
                'traceId' => $entry->correlationId ?? '',
            ];
        }

        if ($otlpLogs === []) {
            return;
        }

        $payload = [
            'resourceLogs' => [[
                'resource' => [
                    'attributes' => [
                        ['key' => 'service.name', 'value' => ['stringValue' => $this->serviceName]],
                    ],
                ],
                'scopeLogs' => [[
                    'scope' => [
                        'name' => 'lattice-observability',
                        'version' => '1.0.0',
                    ],
                    'logRecords' => $otlpLogs,
                ]],
            ]],
        ];

        $this->send('/v1/logs', $payload);
    }

    public function getEndpoint(): string
    {
        return $this->endpoint;
    }

    public function getServiceName(): string
    {
        return $this->serviceName;
    }

    public function getSampleRate(): float
    {
        return $this->sampleRate;
    }

    /**
     * Convert a Lattice Span to OTLP span format.
     *
     * @return array<string, mixed>
     */
    private function spanToOtlp(Span $span): array
    {
        $otlp = [
            'traceId' => $span->traceId,
            'spanId' => $span->spanId,
            'name' => $span->name,
            'kind' => 1, // SPAN_KIND_INTERNAL
            'startTimeUnixNano' => (string) (int) ($span->startTime * 1e9),
            'endTimeUnixNano' => (string) (int) ($span->endTime * 1e9),
            'status' => [
                'code' => $span->status === 'ok' ? 1 : 2, // STATUS_CODE_OK or STATUS_CODE_ERROR
            ],
            'attributes' => $this->contextToAttributes($span->attributes),
        ];

        if ($span->parentSpanId !== null) {
            $otlp['parentSpanId'] = $span->parentSpanId;
        }

        return $otlp;
    }

    /**
     * Convert key-value labels to OTLP attribute format.
     *
     * @param array<string, string> $labels
     * @return array<int, array{key: string, value: array<string, string>}>
     */
    private function labelsToAttributes(array $labels): array
    {
        $attributes = [];
        foreach ($labels as $key => $value) {
            $attributes[] = [
                'key' => $key,
                'value' => ['stringValue' => (string) $value],
            ];
        }

        return $attributes;
    }

    /**
     * Convert context array to OTLP attribute format.
     *
     * @param array<string, mixed> $context
     * @return array<int, array{key: string, value: array<string, mixed>}>
     */
    private function contextToAttributes(array $context): array
    {
        $attributes = [];
        foreach ($context as $key => $value) {
            $attr = ['key' => (string) $key, 'value' => $this->toOtlpValue($value)];
            $attributes[] = $attr;
        }

        return $attributes;
    }

    /**
     * Convert a PHP value to OTLP attribute value format.
     *
     * @return array<string, mixed>
     */
    private function toOtlpValue(mixed $value): array
    {
        return match (true) {
            is_int($value) => ['intValue' => (string) $value],
            is_float($value) => ['doubleValue' => $value],
            is_bool($value) => ['boolValue' => $value],
            is_array($value) => ['stringValue' => json_encode($value, JSON_UNESCAPED_UNICODE)],
            default => ['stringValue' => (string) $value],
        };
    }

    /**
     * Map PSR-3 log level to OTLP severity number.
     */
    private function logLevelToSeverityNumber(string $level): int
    {
        return match (strtolower($level)) {
            'emergency' => 24,
            'alert' => 23,
            'critical' => 22,
            'error' => 17,
            'warning' => 13,
            'notice' => 10,
            'info' => 9,
            'debug' => 5,
            default => 0,
        };
    }

    private function shouldSample(): bool
    {
        if ($this->sampleRate >= 1.0) {
            return true;
        }
        if ($this->sampleRate <= 0.0) {
            return false;
        }

        return (mt_rand() / mt_getrandmax()) < $this->sampleRate;
    }

    /**
     * Send an OTLP JSON payload to the collector endpoint.
     *
     * @param array<string, mixed> $payload
     */
    private function send(string $path, array $payload): void
    {
        $url = rtrim($this->endpoint, '/') . $path;
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\n",
                'content' => $json,
                'timeout' => $this->timeoutMs / 1000.0,
                'ignore_errors' => true,
            ],
        ]);

        // Fire and forget - don't block on telemetry export failures
        @file_get_contents($url, false, $context);
    }
}
