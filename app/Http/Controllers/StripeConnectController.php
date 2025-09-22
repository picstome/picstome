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

    // Pay handler
    public function pay(Request $request)
    {
        $user = Auth::user();
        $team = $user->currentTeam;

        $validated = $request->validate([
            'amount' => 'required|integer|min:100|max:1000000',
            'description' => 'required|string|max:255',
        ]);

        try {
            $successUrl = route('stripe.connect.pay.success');
            $cancelUrl = route('stripe.connect.pay.cancel');
            $amount = (int) $validated['amount'];
            $description = $validated['description'];
            $checkoutUrl = $this->stripeConnectService->createCheckoutSession($team, $successUrl, $cancelUrl, $amount, $description);
            return Redirect::to($checkoutUrl);
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    // Success handler
    public function paySuccess(Request $request)
    {
        return view('pages.stripe-connect.pay-success');
    }

    // Cancel handler
    public function payCancel(Request $request)
    {
        return view('pages.stripe-connect.pay-cancel');
    }

    // Return URL handler
    public function return(Request $request)
    {
        $user = Auth::user();
        $team = $user->currentTeam;
        $onboardingComplete = $this->stripeConnectService->isOnboardingComplete($team);
        $statusMessage = $onboardingComplete
            ? 'Stripe onboarding complete! You can now receive payouts.'
            : 'Stripe onboarding incomplete. Please finish setup to receive payouts.';

        return redirect()->route('stripe.connect')->with('status', $statusMessage);
    }
}
