<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Health Checks
    |--------------------------------------------------------------------------
    */
    'health' => [
        'enabled' => true,
        'path' => '/health',
        'checks' => [
            'database' => true,
            'cache' => true,
            'queue' => true,
            'storage' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Tracing (OpenTelemetry)
    |--------------------------------------------------------------------------
    */
    'tracing' => [
        'enabled' => (bool) env('TRACING_ENABLED', false),
        'exporter' => env('TRACING_EXPORTER', 'otlp'),
        'endpoint' => env('TRACING_ENDPOINT', 'http://localhost:4318'),
        'service_name' => env('TRACING_SERVICE_NAME', env('APP_NAME', 'lattice')),
        'sample_rate' => (float) env('TRACING_SAMPLE_RATE', 1.0),
    ],

    /*
    |--------------------------------------------------------------------------
    | Metrics (Prometheus)
    |--------------------------------------------------------------------------
    */
    'metrics' => [
        'enabled' => (bool) env('METRICS_ENABLED', false),
        'path' => '/metrics',
        'prefix' => env('METRICS_PREFIX', 'lattice'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Audit Logging
    |--------------------------------------------------------------------------
    */
    'audit' => [
        'enabled' => (bool) env('AUDIT_ENABLED', true),
        'table' => 'audit_logs',
        'retention_days' => (int) env('AUDIT_RETENTION_DAYS', 90),
        'exclude_fields' => ['password', 'password_hash', 'remember_token', 'secret'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Request Logging
    |--------------------------------------------------------------------------
    */
    'request_log' => [
        'enabled' => (bool) env('REQUEST_LOG_ENABLED', false),
        'exclude_paths' => ['/health', '/metrics'],
        'log_body' => (bool) env('REQUEST_LOG_BODY', false),
        'max_body_size' => 10240, // 10KB
    ],
];
