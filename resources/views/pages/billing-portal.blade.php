<?php

use Illuminate\Http\Request;

use function Laravel\Folio\middleware;
use function Laravel\Folio\name;
use function Laravel\Folio\render;

middleware(['auth']);

name('billing-portal');

render(function (Request $request) {
    $team = $request->user()->currentTeam;

    if (! $team->subscribed()) {
        return redirect()->route('subscribe');
    }

    return $team->redirectToBillingPortal(route('galleries'));
}); ?>
