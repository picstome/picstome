<?php

use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Trust the local reverse proxy only outside production.
        if (in_array(env('APP_ENV', 'production'), ['local', 'testing'], true)) {
            $middleware->trustProxies(at: '127.0.0.1');
        }

        $middleware->appendToGroup('web', SetLocale::class);

        $middleware->validateCsrfTokens(except: [
            'stripe/*',
            'webhooks/sftpgo',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // External API clients need the stable `code` field even when the
        // failure happens in middleware before a controller runs.
        $exceptions->render(function (AuthenticationException $exception, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => 'Unauthenticated.',
                    'errors' => (object) [],
                    'code' => 'unauthenticated',
                ], 401);
            }
        });

        $exceptions->reportable(static function (Throwable $e) {
            if (app()->bound('honeybadger')) {
                app('honeybadger')->notify($e, app('request'));
            }
        });
    })->create();
