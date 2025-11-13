# syntax=docker/dockerfile:1.7

# -----------------------------
# Stage 0: Base image with required extensions
# -----------------------------
FROM serversideup/php:8.3-fpm AS base

# Switch to root user to gain permissions for installing extensions
USER root

# Install extensions required by the project's dependencies.
# - bcmath: for Laravel Cashier
# - exif: for spatie/image
# - sockets: for pestphp/pest-plugin-browser
RUN docker-php-ext-install bcmath exif sockets

# Switch back to the default non-root user (ID 1000) for this image.
USER 1000

# -----------------------------
# Stage 1: Composer dependencies
# -----------------------------
FROM base AS composer_stage

# The workdir is owned by root, but composer will run as user 1000.
# We need to change ownership so composer can write the vendor directory.
# Also create and set permissions for the composer cache directory to improve performance.
USER root
WORKDIR /var/www/html
RUN mkdir -p /composer/cache && \
    chown -R 1000:1000 /var/www/html /composer
USER 1000

# Copy composer files and ensure they are owned by the non-root user
COPY --chown=1000:1000 composer.json composer.lock ./

# Allow switching dev/prod dependency install using build-arg
ARG APP_ENV=production
ARG INCLUDE_FLUX=false

# Add flux-pro to composer.json if requested, without installing/updating.
RUN if [ "$INCLUDE_FLUX" = "true" ]; then \
      composer require livewire/flux-pro --no-interaction --no-update; \
    fi

# Update dependencies to ensure the lock file is current and install them.
RUN if [ "$APP_ENV" = "production" ]; then \
      composer update --no-dev --prefer-dist --no-progress --no-interaction --optimize-autoloader; \
    else \
      composer update --prefer-dist --no-progress --no-interaction; \
    fi

# -----------------------------
# Stage 2: Frontend assets
# -----------------------------
FROM --platform=$BUILDPLATFORM node:22-alpine AS frontend
WORKDIR /var/www/html
COPY package.json package-lock.json* ./
RUN npm ci
# Copy vendor directory from composer (needed for some frontend builds)
COPY --from=composer_stage /var/www/html/vendor ./vendor
COPY resources ./resources
COPY vite.config.js postcss.config.js ./
RUN npm run build

# -----------------------------
# Stage 3: Final app image
# -----------------------------
FROM base AS app
WORKDIR /var/www/html

# Copy our custom OPcache configuration into the image (owned by root is fine here)
COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/zz-opcache.ini

# Copy all application files and set ownership to the non-root user
COPY --chown=1000:1000 . .

# Create the SQLite database file before other commands that might need it.
RUN touch database/database.sqlite

# Copy pre-installed composer dependencies and set ownership
COPY --chown=1000:1000 --from=composer_stage /var/www/html/vendor ./vendor

# Copy pre-built frontend assets and set ownership
COPY --chown=1000:1000 --from=frontend /var/www/html/public/build ./public/build

# The serversideup image's entrypoint handles permissions and starts php-fpm.
# We just need to ensure the autoloader is correct after copying everything.
RUN composer dump-autoload --optimize

# Switch to root to set final permissions and copy the entrypoint script
USER root

# Set the correct permissions for the web server (Nginx) to read the files.
RUN chmod -R o+rX /var/www/html

# Copy the entrypoint script and make it executable
COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Set the entrypoint for the container
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]

# Switch back to the non-root user to run the application
USER 1000
