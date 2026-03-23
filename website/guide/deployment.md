---
outline: deep
---

# Deployment

This guide covers deploying LatticePHP to production with PHP-FPM, RoadRunner, or Docker.

## Production Checklist

Before deploying, run through this checklist:

```bash
# 1. Install production dependencies only
composer install --no-dev --optimize-autoloader

# 2. Compile the attribute manifest (zero reflection at runtime)
php bin/lattice compile

# 3. Cache configuration
php bin/lattice config:cache

# 4. Cache routes
php bin/lattice route:cache

# 5. Run migrations
php bin/lattice migrate --force

# 6. Verify the full test suite passes
vendor/bin/phpunit
```

::: danger
Never deploy with `APP_DEBUG=true`. This exposes stack traces, configuration values, and internal paths in error responses. Always set `APP_DEBUG=false` in production.
:::

## Environment Configuration

Set these in your production `.env`:

```bash
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.example.com

# Use asymmetric JWT keys in production
JWT_ALGORITHM=RS256
JWT_PRIVATE_KEY=/path/to/jwt-private.pem
JWT_PUBLIC_KEY=/path/to/jwt-public.pem

# Restrict CORS to your frontends
CORS_ALLOWED_ORIGINS=https://app.example.com,https://admin.example.com

# Use Redis for cache and queue in production
CACHE_STORE=redis
QUEUE_CONNECTION=redis

# Enable observability
TRACING_ENABLED=true
METRICS_ENABLED=true
AUDIT_ENABLED=true
```

## PHP-FPM with Nginx

The simplest production setup. PHP-FPM boots and destroys the application on every request -- no state leakage concerns.

### Nginx Configuration

```nginx
server {
    listen 80;
    server_name api.example.com;
    root /var/www/app/public;
    index index.php;

    # Security headers
    add_header X-Content-Type-Options nosniff;
    add_header X-Frame-Options DENY;
    add_header X-XSS-Protection "1; mode=block";

    location / {
        try_files $uri /index.php$is_args$args;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    # Deny access to dotfiles
    location ~ /\. {
        deny all;
    }
}
```

### PHP-FPM Pool Configuration

```ini
; /etc/php/8.4/fpm/pool.d/lattice.conf
[lattice]
user = www-data
group = www-data
listen = /run/php/php8.4-fpm.sock

pm = dynamic
pm.max_children = 50
pm.start_servers = 5
pm.min_spare_servers = 5
pm.max_spare_servers = 35

; OPcache settings (add to php.ini)
; opcache.enable=1
; opcache.memory_consumption=256
; opcache.max_accelerated_files=20000
; opcache.validate_timestamps=0    ; Disable in production for speed
```

## RoadRunner

RoadRunner keeps your application in memory between requests. The framework boots once, then handles thousands of requests without re-bootstrapping. This dramatically reduces latency.

```bash
composer require lattice/roadrunner
```

### .rr.yaml Configuration

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

See [Runtime](runtime.md) for the full RoadRunner guide including worker lifecycle, memory guards, and state management.

## Docker

### PHP-FPM Dockerfile

```dockerfile
FROM php:8.4-fpm-alpine

RUN apk add --no-cache icu-dev \
    && docker-php-ext-install intl pdo_mysql opcache

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
COPY . /var/www/app
WORKDIR /var/www/app

RUN composer install --no-dev --optimize-autoloader \
    && php bin/lattice compile \
    && php bin/lattice config:cache \
    && php bin/lattice route:cache

# Set correct permissions
RUN chown -R www-data:www-data storage/ \
    && chmod -R 775 storage/

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

RUN composer install --no-dev --optimize-autoloader \
    && php bin/lattice compile \
    && php bin/lattice config:cache \
    && php bin/lattice route:cache \
    && vendor/bin/rr get-binary

EXPOSE 8080
CMD ["./rr", "serve", "-c", ".rr.yaml"]
```

### Docker Compose (Full Stack)

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

  worker:
    build: .
    command: php bin/lattice queue:work --queue=default,emails,workflows
    environment:
      - APP_ENV=production
      - DB_HOST=db
      - REDIS_HOST=redis
    depends_on:
      - db
      - redis

  scheduler:
    build: .
    command: sh -c "while true; do php bin/lattice schedule:run; sleep 60; done"
    environment:
      - APP_ENV=production
      - DB_HOST=db
      - REDIS_HOST=redis
    depends_on:
      - db
      - redis

  db:
    image: postgres:16-alpine
    environment:
      POSTGRES_DB: lattice
      POSTGRES_USER: lattice
      POSTGRES_PASSWORD: secret
    volumes:
      - db_data:/var/lib/postgresql/data

  redis:
    image: redis:7-alpine

volumes:
  db_data:
```

## Health Checks

LatticePHP provides three health endpoints via the `HealthController`:

| Endpoint | Purpose | Use For |
|---|---|---|
| `GET /health` | Full health check (database, cache, queue) | Monitoring dashboards |
| `GET /health/live` | Liveness probe (process is running) | Kubernetes liveness |
| `GET /health/ready` | Readiness probe (can serve traffic) | Kubernetes readiness, load balancer |

Response format:

```json
{
  "status": "up",
  "checks": {
    "database": { "status": "up", "message": "Connected" },
    "cache": { "status": "up", "message": "Available" }
  }
}
```

## CI/CD Pipeline

A minimal GitHub Actions workflow:

```yaml
name: Deploy
on:
  push:
    branches: [main]

jobs:
  deploy:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.4'
      - run: composer install --no-dev --optimize-autoloader
      - run: vendor/bin/phpunit
      - run: php bin/lattice compile
      - run: php bin/lattice config:cache
      - run: php bin/lattice route:cache
      # Deploy to your server/container registry
```

## Performance Optimization

| Optimization | Command | Impact |
|---|---|---|
| Compile attributes | `php bin/lattice compile` | Zero reflection at runtime |
| Cache config | `php bin/lattice config:cache` | No file reads per request |
| Cache routes | `php bin/lattice route:cache` | No route discovery per request |
| Composer optimize | `composer install --optimize-autoloader` | Faster class loading |
| OPcache | Enable in `php.ini` | Compiled bytecode caching |
| RoadRunner | Use `lattice/roadrunner` | App boots once, serves thousands |

::: tip
With all optimizations enabled, a LatticePHP API on RoadRunner handles 5,000+ requests/second on modest hardware. The biggest wins come from `compile` (eliminates attribute reflection) and RoadRunner (eliminates bootstrap per request).
:::

## Next Steps

- [Runtime](runtime.md) -- PHP-FPM vs RoadRunner vs OpenSwoole in detail
- [Observability](observability.md) -- monitoring, tracing, and metrics in production
- [Security](security.md) -- production security checklist
