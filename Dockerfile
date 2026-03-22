# =============================================================================
# LatticePHP Framework — Multi-Stage Dockerfile
# =============================================================================
# Targets:
#   dev         — Full dev environment with Xdebug, Composer, all extensions
#   production  — Lean FPM image, OPcache tuned, no dev dependencies
#   cli         — Production CLI for migrations, queue workers, schedulers
# =============================================================================

# ---------------------------------------------------------------------------
# Base: shared system deps + PHP extensions
# ---------------------------------------------------------------------------
FROM php:8.4-cli AS base

RUN apt-get update && apt-get install -y --no-install-recommends \
    git \
    curl \
    zip \
    unzip \
    libzip-dev \
    libicu-dev \
    libonig-dev \
    libssl-dev \
    libpq-dev \
    libsqlite3-dev \
    libcurl4-openssl-dev \
    && docker-php-ext-install -j$(nproc) \
    pdo \
    pdo_mysql \
    pdo_pgsql \
    pdo_sqlite \
    zip \
    intl \
    mbstring \
    bcmath \
    opcache \
    pcntl \
    sockets \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/pear

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# ---------------------------------------------------------------------------
# Dev: Xdebug, full source, dev dependencies
# ---------------------------------------------------------------------------
FROM base AS dev

RUN pecl install xdebug \
    && docker-php-ext-enable xdebug

COPY docker/php/xdebug.ini /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini
COPY docker/php/php-dev.ini /usr/local/etc/php/conf.d/zz-dev.ini

COPY composer.json ./
RUN composer install --prefer-dist --no-interaction --no-scripts

COPY . .

RUN composer dump-autoload --optimize

EXPOSE 8000

CMD ["php", "bin/lattice", "serve", "--host=0.0.0.0"]

# ---------------------------------------------------------------------------
# Production: FPM, OPcache tuned, no dev deps, non-root user
# ---------------------------------------------------------------------------
FROM php:8.4-fpm AS production

RUN apt-get update && apt-get install -y --no-install-recommends \
    libzip-dev \
    libicu-dev \
    libonig-dev \
    libpq-dev \
    libsqlite3-dev \
    && docker-php-ext-install -j$(nproc) \
    pdo \
    pdo_mysql \
    pdo_pgsql \
    pdo_sqlite \
    zip \
    intl \
    mbstring \
    bcmath \
    opcache \
    pcntl \
    sockets \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/pear

COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/zz-opcache.ini
COPY docker/php/php-prod.ini /usr/local/etc/php/conf.d/zz-prod.ini

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.json ./
RUN composer install --prefer-dist --no-interaction --no-dev --no-scripts --optimize-autoloader

COPY . .
RUN composer dump-autoload --classmap-authoritative --no-dev

RUN addgroup --gid 1000 lattice \
    && adduser --uid 1000 --ingroup lattice --disabled-password --gecos "" lattice \
    && chown -R lattice:lattice /app

USER lattice

EXPOSE 9000

CMD ["php-fpm"]

# ---------------------------------------------------------------------------
# CLI: Production CLI for artisan-like commands, queue workers, schedulers
# ---------------------------------------------------------------------------
FROM base AS cli

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.json ./
RUN composer install --prefer-dist --no-interaction --no-dev --no-scripts --optimize-autoloader

COPY . .
RUN composer dump-autoload --classmap-authoritative --no-dev

RUN addgroup --gid 1000 lattice \
    && adduser --uid 1000 --ingroup lattice --disabled-password --gecos "" lattice \
    && chown -R lattice:lattice /app

USER lattice

ENTRYPOINT ["php", "bin/lattice"]
CMD ["--help"]
