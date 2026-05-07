# =============================================================================
# Payment Gateway — Multi-stage Dockerfile
#
# Stages:
#   base        — PHP extensions + Composer deps (shared cache layer)
#   dev         — base + Xdebug (local development)
#   prod        — base + OPcache + non-root user (production app/fpm)
#   prod-worker — prod + Horizon entrypoint (production queue worker)
#
# Build examples:
#   docker build --target dev  -t payment-gateway:dev  ./backend
#   docker build --target prod -t payment-gateway:prod ./backend
# =============================================================================

# ─── Stage 1: base ────────────────────────────────────────────────────────────
FROM php:8.4-fpm-alpine AS base

WORKDIR /var/www/html

# System deps (single layer for cache efficiency)
RUN apk add --no-cache \
    git \
    curl \
    libpng-dev \
    libxml2-dev \
    zip \
    unzip \
    oniguruma-dev \
    postgresql-dev

# PHP extensions
RUN docker-php-ext-install \
    pdo \
    pdo_pgsql \
    pgsql \
    mbstring \
    exif \
    pcntl \
    bcmath \
    gd \
    xml \
    sockets \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && rm -rf /tmp/pear

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Install dependencies first (better layer caching — only invalidated on lock change)
COPY composer.json composer.lock ./
RUN composer install \
    --no-scripts \
    --no-autoloader \
    --no-interaction \
    --prefer-dist \
    --no-dev

# Copy application source
COPY . .

# Optimized autoloader; ensure writable dirs exist
RUN composer dump-autoload --optimize --no-dev \
    && mkdir -p storage/framework/{sessions,views,cache} storage/logs bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# ─── Stage 2: dev ─────────────────────────────────────────────────────────────
FROM base AS dev

# Xdebug (build deps installed and removed in the same layer)
RUN apk add --no-cache linux-headers ${PHPIZE_DEPS} \
    && pecl install xdebug \
    && docker-php-ext-enable xdebug \
    && apk del ${PHPIZE_DEPS} \
    && rm -rf /tmp/pear

# Also install dev composer deps (needed for tests/phpstan/pint in dev)
RUN composer install --no-scripts --no-interaction --prefer-dist

COPY docker/php/xdebug.ini /usr/local/etc/php/conf.d/xdebug.ini

RUN chown -R www-data:www-data storage bootstrap/cache

EXPOSE 9000

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]

# ─── Stage 3: prod ────────────────────────────────────────────────────────────
FROM base AS prod

COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/opcache.ini

# Dedicated non-root user (principle of least privilege)
RUN addgroup -g 1001 -S appgroup \
    && adduser -u 1001 -S appuser -G appgroup \
    && chown -R appuser:appgroup storage bootstrap/cache

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

USER appuser
EXPOSE 9000
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]

# ─── Stage 4: prod-worker (Laravel Horizon) ───────────────────────────────────
FROM prod AS prod-worker

USER root
COPY docker/entrypoint-worker.sh /usr/local/bin/entrypoint-worker.sh
RUN chmod +x /usr/local/bin/entrypoint-worker.sh
USER appuser

ENTRYPOINT ["/usr/local/bin/entrypoint-worker.sh"]
