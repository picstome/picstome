<?php

use Illuminate\Http\Request;
use Illuminate\View\View;

use function Laravel\Folio\middleware;
use function Laravel\Folio\name;
use function Laravel\Folio\render;

name('product-checkout-success');

middleware(['auth', 'verified']);

render(function (View $view, Request $request) {
    $sessionId = $request->get('session_id');

    if (!$sessionId) {
        return redirect()->route('subscribe');
    }

    $team = $request->user()->currentTeam;
    $checkoutSession = $team->stripe()->checkout->sessions->retrieve($sessionId);

    if ($checkoutSession->payment_status === 'paid') {
        $team->update([
            'lifetime' => true,
            'custom_storage_limit' => config('picstome.subscription_storage_limit'),
        ]);

        return redirect()->route('dashboard')->with('success', 'Payment successful!');
    }

    if (!$sessionId) {
        return redirect()->route('subscribe');
    }
}); ?>
