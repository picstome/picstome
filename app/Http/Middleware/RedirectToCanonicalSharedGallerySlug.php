<?php

namespace App\Http\Middleware;

use App\Models\Gallery;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps shared gallery links working after a gallery is renamed.
 *
 * The public share URL contains the gallery ULID plus a cosmetic slug derived
 * from its current name. When the photographer renames the gallery, links that
 * were already sent to clients keep the old slug. The ULID still identifies the
 * gallery, so we redirect to the canonical URL instead of returning a 404.
 */
class RedirectToCanonicalSharedGallerySlug
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $gallery = $request->route('gallery');
        $slug = $request->route('slug');

        if (
            $gallery instanceof Gallery
            && is_string($slug)
            && $gallery->is_shared
            && $gallery->slug !== $slug
        ) {
            return redirect()->route('shares.show', array_merge($request->query(), [
                'gallery' => $gallery,
                'slug' => $gallery->slug,
            ]));
        }

        return $next($request);
    }
}
