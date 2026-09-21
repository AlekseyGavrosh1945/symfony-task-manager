FROM php:8.2-fpm-alpine

RUN apk add --no-cache icu-dev libzip-dev postgresql-dev shadow \
    && docker-php-ext-install pdo_pgsql intl zip opcache \
    # keep files created in the container owned by the host user (uid 1000)
    && usermod -u 1000 www-data && groupmod -g 1000 www-data

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/app
