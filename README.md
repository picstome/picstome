## Installation

This project is a standard Laravel application; it is based on Laravel 12 and uses Livewire, Tailwind CSS, and TallStackUI for the frontend. If you are familiar with Laravel, you should feel comfortable working on this project.

For local development, you can use the following requirements:

- PHP 8.3 with SQLite, GD, and other common extensions.
- Node.js 16 or later.

> **Note for production:**
> If you expect users to download large galleries or files, you should increase the `request_terminate_timeout` setting in your PHP-FPM pool configuration (usually in `/etc/php/8.3/fpm/pool.d/www.conf`).
> For example:
>
> ```
> request_terminate_timeout = 1200
> ```
>
> This prevents PHP-FPM from killing long-running download requests. Make sure to reload PHP-FPM after changing this setting.
>
> Large uploads copied to S3 from the application can also exceed PHP's default execution limit. The gallery upload action raises the PHP limit to 5 minutes, but the PHP-FPM pool's `request_terminate_timeout` must allow that longer request too.

If you meet these requirements, you can start by cloning the repository and installing the dependencies.

Using [Composer](https://getcomposer.org) and [NPM](https://www.npmjs.com):

```bash
composer install
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

In a **separate terminal**, build the assets in watch mode and start the development server:

```bash
composer run dev
```
