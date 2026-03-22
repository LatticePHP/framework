# Observability

LatticePHP provides structured logging, correlation IDs, distributed tracing, metrics, health endpoints, and audit logging. All classes live in the `lattice/observability` package.

---

## Structured Logging

### Log Facade

The `Log` class (`Lattice\Observability\Log`) is a static facade over `StructuredLogger`.

```php
use Lattice\Observability\Log;

Log::info('Order created', ['orderId' => 123, 'total' => 49.99]);
Log::error('Payment failed', ['orderId' => 123, 'error' => $e->getMessage()]);
Log::warning('Rate limit approaching', ['remaining' => 5]);
Log::debug('Cache hit', ['key' => 'user:42']);
```

All PSR-3 levels are supported: `emergency`, `alert`, `critical`, `error`, `warning`, `notice`, `info`, `debug`.

### StructuredLogger

`Lattice\Observability\Logger\StructuredLogger` implements `Psr\Log\LoggerInterface`. Every log call creates a `LogEntry` with level, message, context, timestamp, and optional correlation ID.

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
| Stream | `Lattice\Observability\Logger\StreamLogHandler` | Write to any stream (`php://stderr`, file path) |
| Daily File | `Lattice\Observability\Logger\DailyFileHandler` | Rotate log files daily with retention |
| Multi-Channel | `Lattice\Observability\Logger\MultiChannelHandler` | Fan out to multiple handlers |
| In-Memory | `Lattice\Observability\Logger\InMemoryLogHandler` | Capture logs in tests |

### DailyFileHandler

```php
use Lattice\Observability\Logger\DailyFileHandler;

$handler = new DailyFileHandler(
    basePath: '/var/log/lattice/app',    // Produces: app-2026-03-22.log
    retentionDays: 14,
);

// Prune old logs (call from scheduler)
$removed = $handler->pruneOldLogs();
```

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

### Logging Configuration

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
    'context' => [
        'correlation_id' => true,
        'tenant_id' => true,
        'workspace_id' => true,
        'user_id' => true,
        'request_path' => true,
    ],
];
```

---

## Correlation IDs

`CorrelationContext` (`Lattice\Observability\CorrelationContext`) carries trace context across request boundaries.

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
    'X-Tenant-ID' => 'tenant_1',
]);

$ctx->getCorrelationId(); // 'abc-123'
$ctx->getTraceId();       // 'trace-456'
$ctx->getTenantId();      // 'tenant_1'
$ctx->toArray();          // All non-null fields as array
```

The `CorrelationIdMiddleware` (`Lattice\Observability\Middleware\CorrelationIdMiddleware`) extracts or generates correlation IDs for every incoming request automatically.

---

## Request Logging

The `RequestLoggingInterceptor` (`Lattice\Observability\Middleware\RequestLoggingInterceptor`) implements `InterceptorInterface` and logs every request with timing, handler info, and status.

```php
// Successful request log:
// Log::info('Request handled', [
//     'handler' => 'OrderController::index',
//     'module' => 'OrdersModule',
//     'correlation_id' => 'abc-123',
//     'duration_ms' => 12.45,
//     'method' => 'GET',
//     'path' => '/api/orders',
//     'status' => 200,
// ]);

// Failed request log:
// Log::error('Request failed', [
//     'handler' => 'OrderController::store',
//     'error' => 'Validation failed',
//     'error_class' => 'Lattice\\Validation\\ValidationException',
//     'duration_ms' => 3.21,
// ]);
```

Enable in `config/observability.php`:

```php
'request_log' => [
    'enabled' => (bool) env('REQUEST_LOG_ENABLED', false),
    'exclude_paths' => ['/health', '/metrics'],
    'log_body' => (bool) env('REQUEST_LOG_BODY', false),
    'max_body_size' => 10240,
],
```

---

## Query Logging

The `QueryLoggingHandler` (`Lattice\Observability\Middleware\QueryLoggingHandler`) tracks database queries and flags slow ones.

```php
use Lattice\Observability\Middleware\QueryLoggingHandler;

$queryLogger = new QueryLoggingHandler(slowQueryThresholdMs: 100.0);

// Called by the database layer:
$queryLogger->log('SELECT * FROM orders WHERE id = ?', [42], timeMs: 5.3);
$queryLogger->log('SELECT * FROM products JOIN ...', [], timeMs: 250.1);
// ^ Logs: "Slow query" warning with SQL and timing

$queryLogger->getQueryCount();  // 2
$queryLogger->getTotalTime();   // 255.4
$queryLogger->getQueries();     // Array of [sql, bindings, time_ms]
$queryLogger->reset();          // Clear between requests (long-running workers)
```

---

## OpenTelemetry Integration

The `OtelExporter` (`Lattice\Observability\OpenTelemetry\OtelExporter`) exports traces, metrics, and logs to any OTLP-compatible collector (Jaeger, Grafana Tempo, Datadog).

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
$exporter->exportSpans([$span1, $span2]);

// Export metrics
$exporter->exportMetrics($metricsCollector->getMetrics());

// Export logs
$exporter->exportLogs([$logEntry1, $logEntry2]);
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

---

## Metrics

The `MetricsCollector` interface (`Lattice\Observability\Metrics\MetricsCollector`) provides counters, gauges, and histograms.

```php
use Lattice\Observability\Metrics\InMemoryMetricsCollector;

$metrics = new InMemoryMetricsCollector();

$metrics->counter('http_requests_total', 1, ['method' => 'GET', 'path' => '/api/orders']);
$metrics->gauge('queue_depth', 42, ['queue' => 'default']);
$metrics->histogram('request_duration_ms', 12.5, ['handler' => 'OrderController::index']);

$all = $metrics->getMetrics(); // Structured array for export
```

Enable the `/metrics` endpoint in `config/observability.php`:

```php
'metrics' => [
    'enabled' => (bool) env('METRICS_ENABLED', false),
    'path' => '/metrics',
    'prefix' => env('METRICS_PREFIX', 'lattice'),
],
```

---

## Health Checks

The `HealthController` (`Lattice\Observability\Health\HealthController`) exposes three endpoints:

| Endpoint | Purpose | Response |
|---|---|---|
| `GET /health` | Full health check with all registered checks | `{"status": "up", "checks": {...}}` |
| `GET /health/live` | Liveness probe (always returns up if process is alive) | `{"status": "up"}` |
| `GET /health/ready` | Readiness probe (fails if any check is down) | `{"status": "up", "checks": {...}}` |

### Custom Health Checks

Implement `HealthCheckInterface` and register with `HealthRegistry`:

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
            // Run a simple query
            $this->db->select('SELECT 1');
            return new HealthResult(status: HealthStatus::Up, message: 'Connected');
        } catch (\Throwable $e) {
            return new HealthResult(status: HealthStatus::Down, message: $e->getMessage());
        }
    }
}

// Register
$registry = new HealthRegistry();
$registry->register(new DatabaseHealthCheck($db));
$registry->register(new CacheHealthCheck($cache));

$results = $registry->toArray();
// {"status": "up", "checks": {"database": {"status": "up", ...}, "cache": {...}}}
```

`HealthStatus` is an enum with values: `Up`, `Down`, `Degraded`.

---

## Audit Logging

### AuditAction Attribute

Mark controller methods for automatic audit logging:

```php
use Lattice\Observability\Audit\Attributes\AuditAction;

#[AuditAction('Created new invoice', category: 'billing')]
public function create(#[Body] CreateInvoiceDto $dto): Response { ... }
```

### AuditLogger

The `AuditLogger` (`Lattice\Observability\Audit\AuditLogger`) writes structured audit events:

```php
use Lattice\Observability\Audit\AuditLogger;
use Lattice\Observability\Audit\AuditEvent;

$audit->log(new AuditEvent(
    type: 'order',
    actor: 'user:42',
    action: 'created',
    target: 'order:123',
    correlationId: $correlationId,
));
```

### AuditLog Model

The `AuditLog` Eloquent model (`Lattice\Observability\Audit\AuditLog`) provides query helpers:

```php
use Lattice\Observability\Audit\AuditLog;

AuditLog::forUser(42)->get();
AuditLog::forModel('Order', 123)->get();
AuditLog::between($startDate, $endDate)->get();
```

Configure in `config/observability.php`:

```php
'audit' => [
    'enabled' => (bool) env('AUDIT_ENABLED', true),
    'table' => 'audit_logs',
    'retention_days' => (int) env('AUDIT_RETENTION_DAYS', 90),
    'exclude_fields' => ['password', 'password_hash', 'remember_token', 'secret'],
],
```
