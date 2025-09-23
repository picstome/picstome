<?php

namespace App\Http\Controllers;

use App\Models\Team;
use Illuminate\Http\Request;
use Facades\App\Services\StripeConnectService;

class HandlePaymentController extends Controller
{
    public function show(Request $request, string $handle, int $amount, string $description)
    {
        $team = Team::where('handle', strtolower($handle))->firstOrFail();

        validator(['amount' => $amount, 'description' => $description], [
            'amount' => ['required', 'integer', 'min:1'],
            'description' => ['required', 'string', 'max:255'],
        ])->validate();

        $successUrl = route('stripe.connect.pay.success');
        $cancelUrl = route('stripe.connect.pay.cancel');

        try {
            $checkoutUrl = StripeConnectService::createCheckoutSession(
                $team,
                $successUrl,
                $cancelUrl,
                $amount,
                $description
            );
        } catch (\Exception $e) {
            abort(500, 'Unable to create Stripe checkout session');
        }

        return redirect()->away($checkoutUrl);
    }
}
