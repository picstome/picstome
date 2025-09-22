<?php

use Facades\App\Services\StripeConnectService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

use function Laravel\Folio\name;
use function Laravel\Folio\render;

name('stripe.connect');

render(function (Request $request) {
    $team = Auth::user()->currentTeam;

    $onboardingUrl = StripeConnectService::createOnboardingLink($team);

    return redirect($onboardingUrl);
});

?>

<div>
    //
</div>
