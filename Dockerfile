# syntax=docker/dockerfile:1

# ---- base: PHP-FPM + nginx + supervisord + Composer -------------------------
FROM php:8.5-fpm AS base

COPY --from=ghcr.io/mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

RUN apt-get update \
    && apt-get install -y --no-install-recommends nginx supervisor \
    && rm -rf /var/lib/apt/lists/* \
    && install-php-extensions pdo_pgsql intl opcache zip bcmath sodium \
    && rm -f /etc/nginx/sites-enabled/default /etc/nginx/conf.d/*.conf

COPY docker/nginx/nginx.conf /etc/nginx/nginx.conf
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY docker/php/php-fpm.conf /usr/local/etc/php-fpm.d/zz-app.conf
COPY docker/supervisor/supervisord.conf /etc/supervisor/supervisord.conf
COPY --chmod=755 docker/php/entrypoint.sh /usr/local/bin/entrypoint.sh

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_HOME=/tmp/composer

WORKDIR /app
EXPOSE 8080

ENTRYPOINT ["entrypoint.sh"]

# ---- dev: Xdebug + PCOV, source mounted as a volume --------------------------
FROM base AS dev

RUN install-php-extensions xdebug pcov

COPY docker/php/php.ini-dev /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/php/xdebug.ini /usr/local/etc/php/conf.d/zz-xdebug.ini

ENV APP_ENV=dev \
    RUN_MIGRATIONS=0 \
    XDEBUG_MODE=off

# ---- prod: dependencies baked in, warmed cache, preload, non-root -----------
FROM base AS prod

ENV APP_ENV=prod \
    APP_DEBUG=0 \
    RUN_MIGRATIONS=1

COPY docker/php/php.ini-prod /usr/local/etc/php/conf.d/zz-app.ini

COPY composer.json composer.lock symfony.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --no-interaction --prefer-dist

COPY bin bin
COPY config config
COPY migrations migrations
COPY public public
COPY src src
COPY translations translations
COPY .env ./

RUN composer install --no-dev --no-interaction --optimize-autoloader --classmap-authoritative \
    && php bin/console cache:warmup --env=prod --no-debug \
    && rm -rf /tmp/composer \
    && useradd --uid 1000 --create-home app \
    && mkdir -p var/log \
    && chown -R app:app var

USER app
