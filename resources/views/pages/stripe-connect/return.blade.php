<?php

use Facades\App\Services\StripeConnectService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

use function Laravel\Folio\name;
use function Laravel\Folio\render;

name('stripe.connect.return');

render(function (Request $request, View $view) {
    $team = Auth::user()->currentTeam;

    $onboardingComplete = StripeConnectService::isOnboardingComplete($team);

    $statusMessage = $onboardingComplete
        ? 'Stripe onboarding complete! You can now receive payouts.'
        : 'Stripe onboarding incomplete. Please finish setup to receive payouts.';

    return $view->with('status', $statusMessage);
});

?>

<div>
    {{ $status }}
</div>
