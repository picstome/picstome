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
        $this->apiKey = config('services.stripe.secret');
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

        $response = Http::withBasicAuth($this->apiKey, '')
            ->asForm()
            ->post('https://api.stripe.com/v1/accounts', [
                'type' => 'express',
                'country' => 'US', // You may want to use $team->country or config
                'email' => $team->stripeEmail(),
                'business_profile[name]' => $team->name,
                'business_profile[url]' => config('app.url'),
                'controller[fees][payer]' => 'application',
                'controller[losses][payments]' => 'application',
                'controller[stripe_dashboard][type]' => 'express',
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

        $response = Http::withBasicAuth($this->apiKey, '')
            ->asForm()
            ->post('https://api.stripe.com/v1/account_links', [
                'account' => $accountId,
                'type' => 'account_onboarding',
                'refresh_url' => route('stripe.connect.refresh'),
                'return_url' => route('stripe.connect.return'),
                'collection_options[fields]' => 'eventually_due',
            ]);

        if (!$response->successful()) {
            Log::error('Stripe account link creation failed', ['response' => $response->body()]);
            throw new \Exception('Unable to create Stripe onboarding link');
        }

        $accountLink = $response->json();
        return $accountLink['url'];
    }
}
