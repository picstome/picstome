<?php

use Illuminate\Http\Request;

use function Laravel\Folio\middleware;
use function Laravel\Folio\name;
use function Laravel\Folio\render;

middleware(['auth', 'verified']);

name('subscribe');

render(function (Request $request) {
    $team = $request->user()->currentTeam;

    if ($team->subscribed()) {
        return $team->redirectToBillingPortal(route('galleries'));
    }

    return $team
        ->newSubscription('default', config('services.stripe.monthly_price_id'))
        ->allowPromotionCodes()
        ->checkout([
            'success_url' => route('galleries'),
            'cancel_url' => route('galleries'),
    ]);
}); ?>
