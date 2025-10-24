FROM php:8.3-fpm-alpine AS base

RUN apk add --no-cache \
    sqlite \
    sqlite-dev \
    libpng-dev \
    libjpeg-turbo-dev \
    libwebp-dev \
    zlib-dev \
    libzip-dev \
    bzip2-dev \
    libsodium-dev

FROM node:22-alpine AS node-builder
FROM composer AS composer-builder

FROM base AS php-build

RUN docker-php-ext-configure gd --with-jpeg --with-webp

RUN docker-php-ext-install pdo
RUN docker-php-ext-install pdo_sqlite
RUN docker-php-ext-install gd
RUN docker-php-ext-install bz2
RUN docker-php-ext-install opcache
RUN docker-php-ext-install sodium
RUN docker-php-ext-install zip

FROM composer-builder AS composer-build

WORKDIR /app
COPY . .

# Laravel cache directories
RUN mkdir -p storage/framework/cache
RUN mkdir -p storage/framework/cache/data
RUN mkdir -p storage/framework/views
RUN mkdir -p storage/framework/sessions
RUN mkdir -p bootstrap/cache

ENV COMPOSER_AUTH=$COMPOSER_AUTH

RUN composer install --no-dev \
    --no-interaction \
    --no-progress \
    --optimize-autoloader \
    --ignore-platform-reqs # Added to the image later

FROM node-builder AS node-build

WORKDIR /app
COPY package*.json ./
RUN npm ci

COPY --from=composer-build /app ./

RUN npm run build

FROM base AS final

WORKDIR /app

COPY --from=php-build /usr/local/lib/php/extensions/ /usr/local/lib/php/extensions/
COPY --from=php-build /usr/local/etc/php/conf.d/ /usr/local/etc/php/conf.d/

COPY --from=node-build /app ./

COPY docker/config/php.ini /usr/local/etc/php/conf.d/custom.ini
COPY docker/config/www.conf /usr/local/etc/php-fpm.d/www-custom.conf

RUN php artisan key:generate
RUN touch database/database.sqlite
RUN php artisan migrate:fresh --seed --force
RUN php artisan storage:link

EXPOSE 9000
EXPOSE 8080

CMD ["php", "artisan", "serve", "--port=8080", "--host=0.0.0.0"]
