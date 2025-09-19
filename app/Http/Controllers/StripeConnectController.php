<?php

namespace App\Http\Controllers;

use App\Services\StripeConnectService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;

class StripeConnectController extends Controller
{
    protected $stripeConnectService;

    public function __construct(StripeConnectService $stripeConnectService)
    {
        $this->stripeConnectService = $stripeConnectService;
    }

    // Main onboarding page
    public function index(Request $request)
    {
        $user = Auth::user();
        $team = $user->currentTeam;
        $onboardingUrl = null;
        if (!$team->stripe_account_id) {
            $onboardingUrl = $this->stripeConnectService->createOnboardingLink($team);
        }
        return view('pages.stripe-connect', compact('team', 'onboardingUrl'));
    }

    // Refresh URL handler
    public function refresh(Request $request)
    {
        $user = Auth::user();
        $team = $user->currentTeam;
        $onboardingUrl = $this->stripeConnectService->createOnboardingLink($team);
        return Redirect::to($onboardingUrl);
    }

    // Return URL handler
    public function return(Request $request)
    {
        // Optionally, check onboarding status and show a message
        return redirect()->route('stripe.connect')->with('status', 'Onboarding complete or saved for later.');
    }
}
