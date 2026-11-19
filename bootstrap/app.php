<?php

use App\Http\Middleware\SetLocale;
use App\Support\SafeRedirect;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;
use Laravel\Sanctum\Http\Middleware\CheckForAnyAbility;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'abilities' => CheckAbilities::class,
            'ability' => CheckForAnyAbility::class,
        ]);

        // Trust the local reverse proxy only outside production.
        if (in_array(env('APP_ENV', 'production'), ['local', 'testing'], true)) {
            $middleware->trustProxies(at: '127.0.0.1');
        }

        $middleware->appendToGroup('web', SetLocale::class);

        // Written by the WordPress marketing site on .picstome.com. They are
        // read-only for the app, so they must not be encrypted by Laravel.
        $middleware->encryptCookies(except: ['ps_attr', 'ps_consent']);

        $middleware->validateCsrfTokens(except: [
            'stripe/webhook',
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

            // Guests opening the verification email must log in first. Keep
            // the signed link for after login and show the return URL on the
            // login screen so campaign/tracking parameters are not lost.
            if ($request->routeIs('verification.verify')) {
                session()->put('verification.url', $request->fullUrl());

                return redirect()->route('login', array_filter([
                    'redirect' => SafeRedirect::sanitize($request->string('redirect')->toString()),
                ]));
            }
        });

        $exceptions->reportable(static function (Throwable $e) {
            if (app()->bound('honeybadger')) {
                app('honeybadger')->notify($e, app('request'));
            }
        });
    })->create();
