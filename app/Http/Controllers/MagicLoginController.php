<?php

namespace App\Http\Controllers;

use App\Services\EmailVerificationService;
use App\Services\MagicLoginLink;
use App\Support\SafeRedirect;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;

/**
 * One-click passwordless login. The emailed link logs the user in directly
 * and sends them to the page the link was created from.
 */
class MagicLoginController extends Controller
{
    public function __invoke(
        Request $request,
        string $token,
        MagicLoginLink $links,
        EmailVerificationService $verification,
    ): RedirectResponse {
        $key = 'magic-login-consume:'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 10)) {
            return redirect()->route('login')
                ->with('status', __('Too many attempts. Please try again shortly.'));
        }

        RateLimiter::hit($key, 60);

        $record = $links->consume($token);

        if ($record === null || $record->user === null) {
            return redirect()->route('login')
                ->with('status', __('That login link is invalid or has expired. Request a new one.'));
        }

        $user = $record->user;

        if (Auth::check() && ! Auth::user()->is($user)) {
            Auth::logout();
        }

        Auth::login($user, remember: true);

        $request->session()->regenerate();

        $verification->markVerified($user);

        return redirect(SafeRedirect::sanitize($record->redirect) ?? '/');
    }
}
