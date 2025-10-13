<?php

namespace App\Services;

use App\Models\Team;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class StripeConnectService
{
    protected $apiKey;

    public function __construct()
    {
        $this->apiKey = config('services.stripe.test_secret');
    }

    /**
     * Ensure the team has a Stripe connected account, create if missing.
     * Returns the Stripe account ID.
     */
    public function ensureConnectedAccount(Team $team): string
    {
        if ($team->stripe_account_id) {
            return $team->stripe_account_id;
        }

        $response = Http::withToken($this->apiKey)
            ->asForm()
            ->withHeaders([
                'Accept' => 'application/json',
            ])
            ->post('https://api.stripe.com/v1/accounts', [
                'email' => $team->stripeEmail(),
            ]);


        if (!$response->successful()) {
            Log::error('Stripe account creation failed', ['response' => $response->body()]);

            throw new \Exception('Unable to create Stripe connected account');
        }

        $account = $response->json();
        $team->stripe_account_id = $account['id'];
        $team->save();

        return $account['id'];
    }

    /**
     * Create an Account Link for onboarding.
     * Returns the onboarding URL.
     */
    public function createOnboardingLink(Team $team): string
    {
        $accountId = $this->ensureConnectedAccount($team);

        $response = Http::withToken($this->apiKey)
            ->asForm()
            ->withHeaders([
                'Accept' => 'application/json',
            ])
            ->post('https://api.stripe.com/v1/account_links', [
                'account' => $accountId,
                'refresh_url' => route('stripe.connect.refresh'),
                'return_url' => route('stripe.connect.return'),
                'type' => 'account_onboarding',
            ]);

        if (!$response->successful()) {
            Log::error('Stripe account link creation failed', ['response' => $response->body()]);

            throw new \Exception('Unable to create Stripe onboarding link');
        }

        $accountLink = $response->json();

        return $accountLink['url'];
    }

    /**
     * Create a Stripe Checkout Session for $15 using the connected account.
     * Returns the session URL.
     */
    public function createCheckoutSession(Team $team, string $successUrl, string $cancelUrl, int $amount, string $description): string
    {
        if (!$team->stripe_account_id) {
            throw new \Exception('Team does not have a Stripe connected account.');
        }

        $commissionPercent = config('picstome.stripe_commission_percent');
        $applicationFee = round($amount * $commissionPercent / 100);

        $response = Http::withToken($this->apiKey)
            ->asForm()
            ->withHeaders([
                'Accept' => 'application/json',
                'Stripe-Account' => $team->stripe_account_id,
            ])
            ->post('https://api.stripe.com/v1/checkout/sessions', [
                'mode' => 'payment',
                'success_url' => $successUrl,
                'cancel_url' => $cancelUrl,
                'line_items[0][price_data][currency]' => $team->stripe_currency,
                'line_items[0][price_data][product_data][name]' => $description,
                'line_items[0][price_data][unit_amount]' => $amount,
                'line_items[0][quantity]' => 1,
                // 'payment_intent_data[application_fee_amount]' => $applicationFee,
            ]);

        if (!$response->successful()) {
            Log::error('Stripe Checkout Session creation failed', ['response' => $response->body()]);

            throw new \Exception('Unable to create Stripe Checkout Session');
        }

        $session = $response->json();

        return $session['url'];
    }

    /**
     * Check if Stripe onboarding is complete for the team.
     * Returns true if no requirements are currently due.
     */
    public function isOnboardingComplete(Team $team): bool
    {
        if (!$team->stripe_account_id) {
            return false;
        }

        $response = Http::withToken($this->apiKey)
            ->withHeaders([
                'Accept' => 'application/json',
            ])
            ->get('https://api.stripe.com/v1/accounts/' . $team->stripe_account_id);

        if (!$response->successful()) {
            Log::error('Stripe account fetch failed', ['response' => $response->body()]);
            return false;
        }

        $account = $response->json();

        return !empty($account['charges_enabled']);
    }

    /**
     * Retrieve a Stripe Checkout Session by ID for a connected account.
     * Returns the session details as an array.
     */
    public function getCheckoutSession(string $sessionId, string $stripeAccountId): array
    {
        $response = Http::withToken($this->apiKey)
            ->withHeaders([
                'Accept' => 'application/json',
                'Stripe-Account' => $stripeAccountId,
            ])
            ->get("https://api.stripe.com/v1/checkout/sessions/{$sessionId}", ['expand' => ['line_items']]);

        if (!$response->successful()) {
            \Log::error('Stripe Checkout Session fetch failed', ['response' => $response->body()]);
            throw new \Exception('Unable to fetch Stripe Checkout Session');
        }

        return $response->json();
    }

    /**
     * Returns supported Stripe currencies for enabled countries.
     */
    public function supportedCurrencies(): array
    {
        return [
            'AUD', // Australia
            'EUR', // Austria, Belgium, Croatia, Cyprus, Estonia, Finland, France, Germany, Greece, Ireland, Italy, Latvia, Lithuania, Luxembourg, Malta, Netherlands, Portugal, Slovakia, Slovenia, Spain
            'BRL', // Brazil
            'BGN', // Bulgaria
            'CAD', // Canada
            'CZK', // Czech Republic
            'DKK', // Denmark
            'GBP', // Gibraltar, United Kingdom
            'HKD', // Hong Kong
            'HUF', // Hungary
            'INR', // India
            'JPY', // Japan
            'CHF', // Liechtenstein, Switzerland
            'MYR', // Malaysia
            'MXN', // Mexico
            'NZD', // New Zealand
            'NGN', // Nigeria
            'NOK', // Norway
            'PLN', // Poland
            'RON', // Romania
            'SGD', // Singapore
            'SEK', // Sweden
            'THB', // Thailand
            'AED', // United Arab Emirates
            'USD', // United States
            'ZAR', // South Africa
        ];
    }
}
