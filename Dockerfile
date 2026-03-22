FROM php:8.4-cli

# Install system dependencies
RUN apt-get update && apt-get install -y \
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
    && docker-php-ext-install \
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
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /app

# Copy framework source
COPY . /app

# Install dependencies
RUN composer install --no-interaction --prefer-dist 2>/dev/null || true

CMD ["php", "-v"]
