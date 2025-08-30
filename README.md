<!--
  README consolidated and updated to include Docker usage with a multi-stage build.
-->

## Installation

This project is a standard Laravel application; it is based on Laravel 12 and uses Livewire, Tailwind CSS, and Flux Pro (commercial) for the frontend. If you are familiar with Laravel, you should feel comfortable working on this project. Note: A valid Flux Pro license is required.

For local development, you can use the following requirements:

- PHP 8.3 with SQLite, GD, and other common extensions.
- Node.js 16 or later.
- A valid [Flux Pro](https://fluxui.dev/pricing) license

> **Note for production:**
> If you expect users to download large galleries or files, you should increase the `request_terminate_timeout` setting in your PHP-FPM pool configuration (usually in `/etc/php/8.3/fpm/pool.d/www.conf`).
> For example:
>
> ```
> request_terminate_timeout = 1200
> ```
>
> This prevents PHP-FPM from killing long-running download requests. Make sure to reload PHP-FPM after changing this setting.

If you meet these requirements, you can start by cloning the repository and installing the dependencies.

Using [Composer](https://getcomposer.org) and [NPM](https://www.npmjs.com):

```bash
composer install
composer require livewire/flux-pro

npm install
```

After that, set up your `.env` file:

```bash
cp .env.example .env
php artisan key:generate
```

Set up your database, run the migrations and the seeder:

```bash
touch database/database.sqlite
php artisan migrate:fresh --seed
```

Link the storage to the public folder.

```bash
php artisan storage:link
```

In a separate terminal, build the assets in watch mode and start the development server:

```bash
composer run dev
```

---

## Docker (recommended for production-like runs)

This repository includes a multi-stage Docker build and a docker-compose setup using:
- php-fpm (php:8.3-fpm-alpine) with required extensions (pdo_sqlite, gd, exif, bcmath, intl, opcache)
- nginx as reverse proxy serving the public/ directory
- redis (optional, used by the app)

The Docker image compiles PHP dependencies (Composer) and frontend assets (Vite) during build, following best practices.

### 1) Build and start

```bash
# Build the application image
docker compose build

# Start the stack (nginx on :8000)
docker compose up -d

# Check logs
docker compose logs -f web
```

Open http://localhost:8000 in your browser.

### 2) First-time app initialization

```bash
# Copy env and generate app key (run once)
docker compose exec app cp .env.example .env
docker compose exec app php artisan key:generate

# Ensure SQLite file exists and run migrations/seeders
docker compose exec app sh -lc 'mkdir -p database && touch database/database.sqlite'
docker compose exec app php artisan migrate --seed

# Link storage
docker compose exec app php artisan storage:link
```

Persisted data:
- storage/ is persisted in a named volume (storage)
- database/ (including database.sqlite) is persisted in a named volume (sqlite_data)

### 3) Asset builds during Docker image build

The Dockerfile runs the Node build (Vite) as part of the image build. If you modify frontend assets, rebuild the image:

```bash
docker compose build --no-cache app && docker compose up -d
```

For active frontend development with hot-reload, you may still prefer running `npm run dev` locally and using the non-Docker development workflow.

### 4) Notes and tuning

- To change environment (prod/dev) at build time, pass an ARG:
  ```bash
  docker compose build --build-arg APP_ENV=production
  ```
  The compose file defaults to production for smaller images.
- PHP-FPM timeout for large downloads: see the note above. Inside the app container, the FPM pool config path is `/usr/local/etc/php-fpm.d/www.conf`.
- Redis is available inside the network as `redis` (compose service name). Make sure `REDIS_HOST=redis` in your `.env` if you use queues/cache.

### 5) Stop and clean up

```bash
docker compose down
# Remove named volumes too (including SQLite DB and storage)
docker compose down -v
```
