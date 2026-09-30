FROM php:8.3-fpm-alpine

RUN apk add --no-cache libzip-dev \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql zip \
    && rm -rf /tmp/*

COPY docker/php/php.ini /usr/local/etc/php/conf.d/app.ini
WORKDIR /var/www/html

