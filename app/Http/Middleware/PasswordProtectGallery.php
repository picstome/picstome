<?php

namespace App\Http\Middleware;

use App\Models\Gallery;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PasswordProtectGallery
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $gallery = $request->route('gallery');

        if (! $gallery instanceof Gallery) {
            $gallery = Gallery::where('ulid', $gallery)->firstOrFail();
        }

        if ($gallery->share_password && $request->session()->get('unlocked_gallery_ulid') !== $gallery->ulid) {
            return redirect()->to(route('shares.unlock', ['gallery' => $gallery]));
        }

        return $next($request);
    }
}
