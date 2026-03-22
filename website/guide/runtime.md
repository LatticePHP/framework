---
outline: deep
---

# Runtime

LatticePHP supports multiple PHP runtimes. Your application code does not change between environments -- the runtime adapter handles the differences.

## Runtime Options

| Runtime | Status | Use Case |
|---|---|---|
| PHP-FPM | Baseline | Standard hosting, shared environments, simplest deployment |
| RoadRunner | First-class | High-performance long-running workers, recommended for production |
| OpenSwoole | Experimental | Coroutine-based async, advanced use cases |

## PHP-FPM (Baseline)

PHP-FPM is the default runtime. No additional packages required.

### Development Server

```bash
php lattice serve
# Starts PHP built-in server on http://localhost:8000

php lattice serve --port=3000
# Custom port
```

### Production with Nginx

```nginx
server {
    listen 80;
    server_name api.example.com;
    root /var/www/app/public;
    index index.php;

    location / {
        try_files $uri /index.php$is_args$args;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

PHP-FPM boots and destroys the application on every request. Simple, predictable, no state leakage.

## RoadRunner (First-Class)

RoadRunner keeps your PHP application in memory between requests. The framework boots once, then handles thousands of requests without re-bootstrapping.

### Installation

```bash
composer require lattice/roadrunner
```

### Key Classes

**RoadRunnerConfig** -- Worker configuration:

```php
use Lattice\RoadRunner\RoadRunnerConfig;

$config = new RoadRunnerConfig(
    httpWorkers: 4,
    grpcWorkers: 4,
    maxMemory: 128,          // MB per worker
    resetProviders: [        // Providers to reset between requests
        CacheServiceProvider::class,
    ],
);
```

**WorkerLifecycle** -- Hooks into the worker lifecycle:

```php
use Lattice\RoadRunner\WorkerLifecycle;

$lifecycle = new WorkerLifecycle();

$lifecycle->onStartup(function (): void {
    // Called once when the worker process starts
});

$lifecycle->onRequest(function (): void {
    // Called after each request completes
});

$lifecycle->onDrain(function (): void {
    // Called when the worker is draining
});

$lifecycle->onShutdown(function (): void {
    // Called when the worker is stopping
});
```

**MemoryGuard** -- Prevents memory leaks from crashing workers:

```php
use Lattice\RoadRunner\MemoryGuard;

$guard = new MemoryGuard();
$guard->check(128); // true if usage >= 128 MB
```

### RoadRunner .rr.yaml

```yaml
version: "3"

server:
  command: "php worker.php"

http:
  address: "0.0.0.0:8080"
  pool:
    num_workers: 4
    max_jobs: 1000
    allocate_timeout: 10s
    destroy_timeout: 10s

logs:
  mode: production
  level: info
```

### Common Pitfalls

1. **Static state persists between requests.** Clear static properties in reset callbacks.
2. **Database connections may timeout.** Configure connection pool timeouts.
3. **File handles leak.** Close all file handles in request callbacks.
4. **Memory grows gradually.** Set `maxMemory` to catch leaks.

## OpenSwoole (Experimental)

OpenSwoole provides coroutine-based concurrency. This runtime is experimental.

```bash
composer require lattice/openswoole
```

## Docker Deployment

### PHP-FPM Dockerfile

```dockerfile
FROM php:8.4-fpm-alpine

RUN apk add --no-cache icu-dev \
    && docker-php-ext-install intl pdo_mysql opcache

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
COPY . /var/www/app
WORKDIR /var/www/app

RUN composer install --no-dev --optimize-autoloader
RUN php lattice compile

EXPOSE 9000
CMD ["php-fpm"]
```

### RoadRunner Dockerfile

```dockerfile
FROM php:8.4-cli-alpine

RUN apk add --no-cache icu-dev \
    && docker-php-ext-install intl pdo_mysql opcache pcntl

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
COPY . /var/www/app
WORKDIR /var/www/app

RUN composer install --no-dev --optimize-autoloader
RUN php lattice compile
RUN vendor/bin/rr get-binary

EXPOSE 8080
CMD ["./rr", "serve", "-c", ".rr.yaml"]
```

### Docker Compose

```yaml
services:
  api:
    build: .
    ports:
      - "8080:8080"
    environment:
      - APP_ENV=production
      - DB_HOST=db
      - REDIS_HOST=redis
    depends_on:
      - db
      - redis
    healthcheck:
      test: ["CMD", "curl", "-f", "http://localhost:8080/health/live"]
      interval: 10s
      timeout: 5s
      retries: 3

  db:
    image: mysql:8.0
    environment:
      MYSQL_DATABASE: lattice
      MYSQL_ROOT_PASSWORD: secret
    volumes:
      - db_data:/var/lib/mysql

  redis:
    image: redis:7-alpine

volumes:
  db_data:
```

## Choosing a Runtime

| Factor | PHP-FPM | RoadRunner | OpenSwoole |
|---|---|---|---|
| Setup complexity | Low | Medium | High |
| Performance | Good | Excellent | Excellent |
| Memory efficiency | High (per-request) | Medium (pooled) | Medium (pooled) |
| State management | No concerns | Must reset state | Must reset state |
| Debugging | Standard tools | Standard tools | Coroutine-aware needed |
| Production readiness | Proven | Proven | Experimental |

Start with PHP-FPM. Move to RoadRunner when you need lower latency and higher throughput. Consider OpenSwoole only for specific async use cases.
