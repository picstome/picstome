<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()) {
            app()->setLocale($this->parseHttpLocale($request)
                ?? config('app.locale'));

            return $next($request);
        }

        app()->setLocale($request->user()->language
            ?? $this->parseHttpLocale($request)
            ?? config('app.locale'));

        return $next($request);
    }

    private function parseHttpLocale(Request $request): string
    {
        $list = explode(',', $request->server('HTTP_ACCEPT_LANGUAGE', ''));

        $locales = Collection::make($list)
            ->map(function ($locale) {
                $parts = explode(';', $locale);

                $mapping['locale'] = trim($parts[0]);

                if (isset($parts[1])) {
                    $factorParts = explode('=', $parts[1]);

                    $mapping['factor'] = $factorParts[1];
                } else {
                    $mapping['factor'] = 1;
                }

                return $mapping;
            })
            ->sortByDesc(function ($locale) {
                return $locale['factor'];
            });

        return Str::of($locales->first()['locale'])->before('-');
    }
}
