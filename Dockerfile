FROM composer:2 AS vendor

WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-scripts --prefer-dist --optimize-autoloader

FROM node:22-alpine AS assets

WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY resources resources
COPY vite.config.js ./
COPY --from=vendor /app/vendor vendor
RUN npm run build

FROM php:8.5-fpm-alpine AS app

RUN apk add --no-cache --virtual .build-deps libpng-dev postgresql-dev \
    && apk add --no-cache libpng libpq \
    && docker-php-ext-install gd pcntl pdo_pgsql \
    && apk del .build-deps

RUN { \
      echo 'opcache.enable=1'; \
      echo 'opcache.validate_timestamps=0'; \
      echo 'opcache.memory_consumption=128'; \
    } > /usr/local/etc/php/conf.d/opcache.ini

WORKDIR /var/www/html
COPY --chown=www-data:www-data . .
COPY --from=vendor --chown=www-data:www-data /app/vendor vendor
COPY --from=assets --chown=www-data:www-data /app/public/build public/build
RUN mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

USER www-data

FROM caddy:2-alpine AS web

WORKDIR /var/www/html
COPY Caddyfile /etc/caddy/Caddyfile
COPY --from=app /var/www/html/public public
