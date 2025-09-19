<?php

use Illuminate\Http\Request;

use function Laravel\Folio\render;

render(function (Request $request) {
    //
});

?>

<div>
    @if ($team->stripe_account_id)
        <p>Your team is connected to Stripe. Account ID: <strong>{{ $team->stripe_account_id }}</strong></p>
    @else
        <p>Your team is not connected to Stripe.</p>
        @if ($onboardingUrl)
            <a href="{{ $onboardingUrl }}" class="btn btn-primary">Connect with Stripe</a>
        @else
            <p>Unable to generate onboarding link. Please try again.</p>
        @endif
    @endif
</div>
