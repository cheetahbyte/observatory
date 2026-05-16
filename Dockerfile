FROM composer:latest AS builder

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --prefer-dist \
    --no-interaction \
    --no-autoloader

COPY . .

RUN composer dump-autoload --classmap-authoritative --no-interaction

FROM php:8.4-fpm-bookworm AS runtime

RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        libpq-dev \
    ; \
    docker-php-ext-install pdo_pgsql; \
    apt-get clean; \
    rm -rf /var/lib/apt/lists/*

COPY docker/php/conf.d/production.ini $PHP_INI_DIR/conf.d/production.ini
COPY docker/php/conf.d/zz-app-pool.conf /usr/local/etc/php-fpm.d/zz-app-pool.conf

RUN set -eux; \
    useradd -m -u 1000 app; \
    mkdir -p /app/storage/cache; \
    chown -R app:app /app

COPY --from=builder /app /app

WORKDIR /app

USER app
