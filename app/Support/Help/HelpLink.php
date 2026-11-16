<?php

namespace App\Support\Help;

/**
 * Turns the current route into a link to the matching help center article.
 *
 * The map lives in `config/help.php`; this class only resolves it and builds
 * the URL for the current locale. Keeping the logic here means the link can be
 * rendered from the layout without adding help code to every page.
 */
class HelpLink
{
    /**
     * URL of the article for the route, or the help center home when there is
     * no specific article.
     */
    public function url(?string $route = null): string
    {
        $route ??= request()->route()?->getName();

        $base = rtrim((string) config('help.url'), '/');
        $prefix = str_starts_with(app()->getLocale(), 'es') ? '/es' : '';
        $slug = $this->slug($route);

        if ($slug === null) {
            return $base.$prefix.'/';
        }

        return $base.$prefix.'/'.trim($slug, '/').'/';
    }

    /**
     * Docs path for a route. Exact match first, then the route's first segment
     * so child routes such as `contracts.show` or `invoices.pdf` use the
     * section article without listing every action.
     */
    public function slug(?string $route): ?string
    {
        if ($route === null) {
            return null;
        }

        $routes = (array) config('help.routes', []);

        if (isset($routes[$route])) {
            return $routes[$route];
        }

        $prefix = str_contains($route, '.') ? str($route)->before('.')->toString() : null;

        return $prefix === null ? null : ($routes[$prefix] ?? null);
    }
}
