<!--
  README consolidated and updated to include Docker usage with a multi-stage build.
-->

## Introduction

This project is a standard Laravel application based on Laravel 12. It uses Livewire, Tailwind CSS, and Flux Pro for the frontend.

The recommended way to run this application is by using the provided Docker setup, which ensures a consistent and reproducible production-like environment.

---

## Standalone Installation

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

## Docker Installation (Recommended)

This repository includes a fully automated, multi-stage Docker build and a `docker-compose` setup. The environment consists of:
- **app**: A `serversideup/php:8.3-fpm` image with all required PHP extensions.
- **web**: An `nginx` container acting as a reverse proxy.
- **redis**: A `redis` container for caching and queues.

The Docker image compiles all PHP dependencies (Composer) and frontend assets (Vite) during the build process.

### 1) First-Time Setup

Before starting, you need a `.env` file.

```bash
# Create your environment file from the example
cp .env.example .env
```
Make sure to configure your database, cache, and other settings in this file as needed. The default setup is configured to use SQLite and Redis.

### 2) Build and Start the Application

With Docker and Docker Compose installed, you can build and run the entire application stack with a single command.

```bash
# Build the images and start the services in the background
docker compose up --build -d
```

Open **http://localhost:8000** in your browser.

### How It Works: Automated Initialization

The first time the `app` container starts, a custom entrypoint script will automatically:
1.  Generate an `APP_KEY` if one is not already set in your `.env` file.
2.  Create the `public/storage` symbolic link.
3.  Run database migrations (`php artisan migrate`).
4.  Seed the database if `APP_ENV` is not set to `production`.
5.  Cache Laravel's configuration, routes, and views for optimal performance in production.

### 3) Interacting with the Application

To run Artisan commands, use `docker compose exec`:

```bash
# Example: Tail the application log
docker compose exec app tail -f /var/www/html/storage/logs/laravel.log

# Example: Run a specific seeder
docker compose exec app php artisan db:seed --class=MySeeder
```

### 4) Development and Asset Rebuilding

The Dockerfile runs the frontend asset build (`npm run build`) as part of the image creation. If you make changes to your JavaScript or CSS files, you will need to rebuild the image to see them.

```bash
# Rebuild the app image and restart the services
docker compose up --build -d
```

For active frontend development with hot-reloading, you may prefer a local Node.js environment and running `npm run dev` outside of Docker.

### 5) Stop and Clean Up

```bash
# Stop the containers
docker compose down

# Stop containers AND remove all associated volumes (database, storage, etc.)
docker compose down -v
```

---
