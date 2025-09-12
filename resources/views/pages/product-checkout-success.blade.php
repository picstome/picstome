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
        return redirect()->route('subscribe')->with('error', 'No session ID provided.');
    }

    $team = $request->user()->currentTeam;
    $checkoutSession = $team->stripe()->checkout->sessions->retrieve($sessionId);

    if ($checkoutSession->payment_status === 'paid') {
        $team->update([
            'lifetime' => true,
            'custom_storage_limit' => config('picstome.subscription_storage_limit'),
        ]);
    }

    $view->with('paymentSuccess', $checkoutSession->payment_status === 'paid');
}); ?>

<div>
    @if(isset($paymentSuccess) && $paymentSuccess)
        <h1>Thank you for your purchase! Your team now has a lifetime subscription.</h1>
    @else
        <h1>There was a problem with your payment.</h1>
    @endif
</div>
