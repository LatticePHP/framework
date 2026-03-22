---
outline: deep
---

# Observability

LatticePHP provides structured logging, correlation IDs, distributed tracing, metrics, health endpoints, and audit logging. All classes live in the `lattice/observability` package.

## Structured Logging

### Log Facade

The `Log` class is a static facade over `StructuredLogger`.

```php
use Lattice\Observability\Log;

Log::info('Order created', ['orderId' => 123, 'total' => 49.99]);
Log::error('Payment failed', ['orderId' => 123, 'error' => $e->getMessage()]);
Log::warning('Rate limit approaching', ['remaining' => 5]);
Log::debug('Cache hit', ['key' => 'user:42']);
```

All PSR-3 levels are supported: `emergency`, `alert`, `critical`, `error`, `warning`, `notice`, `info`, `debug`.

### StructuredLogger

```php
use Lattice\Observability\Logger\StructuredLogger;
use Lattice\Observability\Logger\StreamLogHandler;

$logger = new StructuredLogger(
    handler: new StreamLogHandler('php://stderr'),
    correlationId: 'req_abc123',
);

$logger->info('Request started', ['path' => '/api/orders']);
```

### Log Handlers

| Handler | Class | Purpose |
|---|---|---|
| Stream | `StreamLogHandler` | Write to any stream (`php://stderr`, file path) |
| Daily File | `DailyFileHandler` | Rotate log files daily with retention |
| Multi-Channel | `MultiChannelHandler` | Fan out to multiple handlers |
| In-Memory | `InMemoryLogHandler` | Capture logs in tests |

### Multi-Channel Logging

```php
use Lattice\Observability\Logger\MultiChannelHandler;
use Lattice\Observability\Logger\StreamLogHandler;
use Lattice\Observability\Logger\DailyFileHandler;

$multi = new MultiChannelHandler();
$multi->addHandler('stderr', new StreamLogHandler('php://stderr'));
$multi->addHandler('daily', new DailyFileHandler('/var/log/lattice/app', retentionDays: 14));

$logger = new StructuredLogger($multi);
// Every log entry goes to both handlers
```

### Configuration

In `config/logging.php`:

```php
return [
    'default' => env('LOG_CHANNEL', 'stack'),
    'channels' => [
        'stack' => [
            'driver' => 'stack',
            'channels' => explode(',', env('LOG_STACK', 'daily,stderr')),
        ],
        'daily' => [
            'driver' => 'daily',
            'path' => storage_path('logs/lattice.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => (int) env('LOG_DAYS', 14),
        ],
        'stderr' => [
            'driver' => 'stderr',
            'level' => env('LOG_LEVEL', 'debug'),
            'formatter' => env('LOG_STDERR_FORMATTER', 'json'),
        ],
    ],
];
```

## Correlation IDs

`CorrelationContext` carries trace context across request boundaries.

```php
use Lattice\Observability\CorrelationContext;

// Generate a new context
$ctx = CorrelationContext::generate();
$ctx->getCorrelationId(); // UUID v4

// Build from incoming HTTP headers
$ctx = CorrelationContext::fromHeaders([
    'X-Correlation-ID' => 'abc-123',
    'X-Trace-ID' => 'trace-456',
    'X-Span-ID' => 'span-789',
]);

$ctx->getCorrelationId(); // 'abc-123'
$ctx->getTraceId();       // 'trace-456'
```

The `CorrelationIdMiddleware` extracts or generates correlation IDs for every incoming request automatically.

## Request Logging

The `RequestLoggingInterceptor` implements `InterceptorInterface` and logs every request with timing, handler info, and status.

```php
// Logs on each request:
// Log::info('Request handled', [
//     'handler' => 'OrderController::index',
//     'correlation_id' => 'abc-123',
//     'duration_ms' => 12.45,
//     'method' => 'GET',
//     'path' => '/api/orders',
//     'status' => 200,
// ]);
```

## Query Logging

The `QueryLoggingHandler` tracks database queries and flags slow ones.

```php
use Lattice\Observability\Middleware\QueryLoggingHandler;

$queryLogger = new QueryLoggingHandler(slowQueryThresholdMs: 100.0);

$queryLogger->log('SELECT * FROM orders WHERE id = ?', [42], timeMs: 5.3);
$queryLogger->log('SELECT * FROM products JOIN ...', [], timeMs: 250.1);
// ^ Logs: "Slow query" warning with SQL and timing

$queryLogger->getQueryCount();  // 2
$queryLogger->getTotalTime();   // 255.4
```

## OpenTelemetry Integration

The `OtelExporter` exports traces, metrics, and logs to any OTLP-compatible collector (Jaeger, Grafana Tempo, Datadog).

```php
use Lattice\Observability\OpenTelemetry\OtelExporter;

$exporter = new OtelExporter(
    endpoint: 'http://localhost:4318',
    serviceName: 'order-service',
    sampleRate: 1.0,
    timeoutMs: 5000,
);

// Export traces
$exporter->exportSpan($span);

// Export metrics
$exporter->exportMetrics($metricsCollector->getMetrics());
```

Configure in `config/observability.php`:

```php
'tracing' => [
    'enabled' => (bool) env('TRACING_ENABLED', false),
    'endpoint' => env('TRACING_ENDPOINT', 'http://localhost:4318'),
    'service_name' => env('TRACING_SERVICE_NAME', env('APP_NAME', 'lattice')),
    'sample_rate' => (float) env('TRACING_SAMPLE_RATE', 1.0),
],
```

## Metrics

The `MetricsCollector` interface provides counters, gauges, and histograms.

```php
use Lattice\Observability\Metrics\InMemoryMetricsCollector;

$metrics = new InMemoryMetricsCollector();

$metrics->counter('http_requests_total', 1, ['method' => 'GET', 'path' => '/api/orders']);
$metrics->gauge('queue_depth', 42, ['queue' => 'default']);
$metrics->histogram('request_duration_ms', 12.5, ['handler' => 'OrderController::index']);
```

Enable the `/metrics` endpoint in `config/observability.php`:

```php
'metrics' => [
    'enabled' => (bool) env('METRICS_ENABLED', false),
    'path' => '/metrics',
    'prefix' => env('METRICS_PREFIX', 'lattice'),
],
```

## Health Checks

The `HealthController` exposes three endpoints:

| Endpoint | Purpose | Response |
|---|---|---|
| `GET /health` | Full health check | `{"status": "up", "checks": {...}}` |
| `GET /health/live` | Liveness probe | `{"status": "up"}` |
| `GET /health/ready` | Readiness probe | `{"status": "up", "checks": {...}}` |

### Custom Health Checks

```php
use Lattice\Observability\Health\HealthCheckInterface;
use Lattice\Observability\Health\HealthResult;
use Lattice\Observability\Health\HealthStatus;

final class DatabaseHealthCheck implements HealthCheckInterface
{
    public function getName(): string
    {
        return 'database';
    }

    public function check(): HealthResult
    {
        try {
            $this->db->select('SELECT 1');
            return new HealthResult(status: HealthStatus::Up, message: 'Connected');
        } catch (\Throwable $e) {
            return new HealthResult(status: HealthStatus::Down, message: $e->getMessage());
        }
    }
}
```

## Audit Logging

### AuditAction Attribute

Mark controller methods for automatic audit logging:

```php
use Lattice\Observability\Audit\Attributes\AuditAction;

#[AuditAction('Created new invoice', category: 'billing')]
public function create(#[Body] CreateInvoiceDto $dto): Response { ... }
```

### AuditLog Model

The `AuditLog` Eloquent model provides query helpers:

```php
use Lattice\Observability\Audit\AuditLog;

AuditLog::forUser(42)->get();
AuditLog::forModel('Order', 123)->get();
AuditLog::between($startDate, $endDate)->get();
```
