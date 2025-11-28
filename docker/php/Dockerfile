FROM php:8.3-fpm

# system deps
RUN apt-get update && apt-get install -y \
    git curl zip unzip libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql

# install composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# config
COPY symfony.ini /usr/local/etc/php/conf.d/

WORKDIR /var/www/html
