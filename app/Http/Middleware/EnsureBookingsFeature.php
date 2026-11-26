<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate the booking tool behind the feature flag.
 *
 * While `bookings_feature_admin_only` is on, only administrators reach these
 * screens. Everyone else is sent to the "coming soon" page, which becomes a
 * redirect to the agenda as soon as the flag is published.
 */
class EnsureBookingsFeature
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->canSeeBookingsFeature()) {
            return redirect()->route('bookings.coming-soon');
        }

        return $next($request);
    }
}
