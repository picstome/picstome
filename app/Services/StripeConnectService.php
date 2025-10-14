<?php

namespace App\Services;

use App\Models\Team;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class StripeConnectService
{
    /**
     * Get the correct Stripe API key for the team (test or live).
     */
    private function getApiKeyForTeam(Team $team): string
    {
        return $team->stripe_test_mode ?
            config('services.stripe.test_secret') :
            config('services.stripe.live_secret');
    }

    /**
     * Get the correct Stripe account ID for the team (test or live).
     */
    private function getStripeAccountIdForTeam(Team $team): ?string
    {
        return $team->stripe_test_mode
            ? $team->stripe_test_account_id
            : $team->stripe_account_id;
    }

    /**
     * Ensure the team has a Stripe connected account, create if missing.
     * Returns the Stripe account ID.
     */
    public function ensureConnectedAccount(Team $team): string
    {
        $accountId = $this->getStripeAccountIdForTeam($team);

        if ($accountId) {
            return $accountId;
        }

        $response = Http::withToken($this->getApiKeyForTeam($team))
            ->asForm()
            ->withHeaders([
                'Accept' => 'application/json',
            ])
            ->post('https://api.stripe.com/v1/accounts', [
                'email' => $team->stripeEmail(),
            ]);

        if (! $response->successful()) {
            Log::error('Stripe account creation failed', ['response' => $response->body()]);

            throw new \Exception('Unable to create Stripe connected account');
        }

        $account = $response->json();

        if ($team->stripe_test_mode) {
            $team->stripe_test_account_id = $account['id'];
        } else {
            $team->stripe_account_id = $account['id'];
        }

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

        $response = Http::withToken($this->getApiKeyForTeam($team))
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

        if (! $response->successful()) {
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
    public function createCheckoutSession(Team $team, string $successUrl, string $cancelUrl, int $amount, string $description, array $metadata = []): string
    {
        $accountId = $this->getStripeAccountIdForTeam($team);

        if (! $accountId) {
            throw new \Exception('Team does not have a Stripe connected account.');
        }

        $commissionPercent = config('picstome.stripe_commission_percent');

        $applicationFee = round($amount * $commissionPercent / 100);

        $response = Http::withToken($this->getApiKeyForTeam($team))
            ->asForm()
            ->withHeaders([
                'Accept' => 'application/json',
                'Stripe-Account' => $accountId,
            ])
            ->post('https://api.stripe.com/v1/checkout/sessions', [
                'payment_method_types[]' => 'card',
                'metadata[photoshoot_id]' => $metadata['photoshoot_id'] ?? null,
                'mode' => 'payment',
                'success_url' => $successUrl,
                'cancel_url' => $cancelUrl,
                'line_items[0][price_data][currency]' => $team->stripe_currency,
                'line_items[0][price_data][product_data][name]' => $description,
                'line_items[0][price_data][unit_amount]' => $amount,
                'line_items[0][quantity]' => 1,
                // 'payment_intent_data[application_fee_amount]' => $applicationFee,
            ]);

        if (! $response->successful()) {
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
        $accountId = $this->getStripeAccountIdForTeam($team);

        if (! $accountId) {
            return false;
        }

        $response = Http::withToken($this->getApiKeyForTeam($team))
            ->withHeaders([
                'Accept' => 'application/json',
            ])
            ->get('https://api.stripe.com/v1/accounts/'.$accountId);

        if (! $response->successful()) {
            Log::error('Stripe account fetch failed', ['response' => $response->body()]);

            return false;
        }

        $account = $response->json();

        return ! empty($account['charges_enabled']);
    }

    /**
     * Retrieve a Stripe Checkout Session by ID for a connected account.
     * Returns the session details as an array.
     */
    public function getCheckoutSession(Team $team, string $sessionId, ?string $stripeAccountId = null): array
    {
        $accountId = $stripeAccountId ?: $this->getStripeAccountIdForTeam($team);

        $response = Http::withToken($this->getApiKeyForTeam($team))
            ->withHeaders([
                'Accept' => 'application/json',
                'Stripe-Account' => $accountId,
            ])
            ->get("https://api.stripe.com/v1/checkout/sessions/{$sessionId}", ['expand' => ['line_items']]);

        if (! $response->successful()) {
            Log::error('Stripe Checkout Session fetch failed', ['response' => $response->body()]);
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
