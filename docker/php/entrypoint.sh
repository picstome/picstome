#!/bin/sh
set -e

# Change to the application directory
cd /var/www/html

# Check if the APP_KEY is set. If not, generate it.
if ! grep -q "APP_KEY=base64:" .env; then
    echo "Generating application key..."
    php artisan key:generate
fi

# Check if the storage link exists. If not, create it.
if [ ! -L "/var/www/html/public/storage" ]; then
    echo "Creating storage link..."
    php artisan storage:link
fi

# Run database migrations.
echo "Running database migrations..."
php artisan migrate --force

# If not in production, seed the database for development.
if [ "$APP_ENV" != "production" ]; then
    echo "Seeding database for development..."
    php artisan db:seed --force
fi

# In a production environment, it's good practice to cache the configuration for speed.
if [ "$APP_ENV" = "production" ]; then
    echo "Caching configuration..."
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
fi

# This is the critical part. The `exec "$@"` command passes control to the Dockerfile's
# CMD. In our case, the serversideup/php image's CMD is `php-fpm`.
# This ensures that after our script runs, the container starts the PHP-FPM process.
exec "$@"
