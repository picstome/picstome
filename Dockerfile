# syntax=docker/dockerfile:1.7

# -----------------------------
# Stage 1: Composer dependencies
# -----------------------------
FROM --platform=$BUILDPLATFORM composer:2 AS composer

WORKDIR /app

# Leverage Docker cache: copy only composer files first
COPY composer.json composer.lock ./

# Allow switching dev/prod dependency install using build-arg
ARG APP_ENV=production
# For prod: no-dev, prefer-dist, no-scripts (scripts can run later in app stage if needed)
RUN if [ "$APP_ENV" = "production" ]; then \
      composer install --no-dev --prefer-dist --no-progress --no-interaction --optimize-autoloader; \
    else \
      composer install --prefer-dist --no-progress --no-interaction; \
    fi

# -----------------------------
# Stage 2: Frontend assets
# -----------------------------
FROM --platform=$BUILDPLATFORM node:22-alpine AS frontend
WORKDIR /app

# Leverage Docker cache for node modules
COPY package.json package-lock.json* ./
RUN npm ci || npm install

# Copy only assets needed for build first for better caching
COPY resources ./resources
COPY vite.config.js postcss.config.js ./

# Build assets (build script expected in package.json)
RUN npm run build || npm run prod || true

# -----------------------------
# Stage 3: Base PHP with extensions
# -----------------------------
FROM php:8.3.25-fpm-alpine3.21 AS php-base

# Install system dependencies and PHP extensions commonly required by Laravel
RUN set -eux; \
    apk add --no-cache \
      bash \
      fcgi \
      icu-data-full icu-dev \
      libpng-dev \
      libjpeg-turbo-dev \
      libwebp-dev \
      freetype-dev \
      oniguruma-dev \
      libzip-dev \
      zlib-dev \
      sqlite-libs sqlite-dev \
      git; \
    docker-php-ext-configure gd \
      --with-freetype \
      --with-jpeg \
      --with-webp; \
    docker-php-ext-install -j"$(nproc)" \
      gd \
      exif \
      bcmath \
      intl \
      pcntl \
      pdo_sqlite \
      zip \
      opcache; \
    apk del --no-cache freetype-dev libjpeg-turbo-dev libpng-dev libwebp-dev icu-dev libzip-dev sqlite-dev || true

# Configure PHP (optional custom php.ini can be added at ./.docker/php/php.ini)
WORKDIR /var/www/html

# Create necessary directories and set permissions
RUN addgroup -g 1000 -S www && adduser -u 1000 -S www -G www; \
    mkdir -p storage bootstrap/cache; \
    chown -R www:www /var/www/html

USER www

EXPOSE 9000

# -----------------------------
# Stage 4: Final app image
# -----------------------------
FROM php-base AS app

USER root

# Copy application code
COPY --link . /var/www/html

# Copy Composer vendor from builder
COPY --from=composer /app/vendor /var/www/html/vendor

# Copy built assets if present
COPY --from=frontend /app/public/build /var/www/html/public/build

# Ensure writable directories
RUN set -eux; \
    mkdir -p storage bootstrap/cache; \
    chown -R www:www storage bootstrap/cache; \
    find storage -type d -exec chmod 775 {} +; \
    chmod -R 775 bootstrap/cache

USER www

# Healthcheck to verify FPM is responding
HEALTHCHECK --interval=30s --timeout=3s --retries=10 CMD SCRIPT_NAME=/ping SCRIPT_FILENAME=/ping REQUEST_METHOD=GET cgi-fcgi -bind -connect 127.0.0.1:9000 || exit 1

CMD ["php-fpm"]
