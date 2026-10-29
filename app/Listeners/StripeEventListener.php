<?php

namespace App\Listeners;

use App\Models\Team;
use Laravel\Cashier\Events\WebhookReceived;

class StripeEventListener
{
    /**
     * Handle the event.
     */
    public function handle(WebhookReceived $event): void
    {
        match ($event->payload['type']) {
            'checkout.session.completed' => $this->handleCheckoutSessionCompleted($event),
            'customer.subscription.created' => $this->handleSubscriptionCreated($event),
            'customer.subscription.updated' => $this->handleSubscriptionUpdated($event),
            'customer.subscription.deleted' => $this->handleSubscriptionDeleted($event),
            default => null,
        };
    }

    /**
     * Handle subscription creation.
     */
    protected function handleSubscriptionCreated(WebhookReceived $event): void
    {
        $team = $this->findTeamByStripeCustomer($event->payload['data']['object']['customer']);

        if ($team) {
            $team->update([
                'custom_storage_limit' => $this->storageLimitFromPayload($event->payload),
                'monthly_contract_limit' => null, // Unlimited contracts
                'monthly_invoice_limit' => null, // Back to the plan default
            ]);
        }
    }

    /**
     * Handle subscription updates (plan changes).
     */
    protected function handleSubscriptionUpdated(WebhookReceived $event): void
    {
        $team = $this->findTeamByStripeCustomer($event->payload['data']['object']['customer']);

        if ($team) {
            $team->update([
                'custom_storage_limit' => $this->storageLimitFromPayload($event->payload),
                'monthly_contract_limit' => null, // Unlimited contracts
                'monthly_invoice_limit' => null, // Back to the plan default
            ]);
        }
    }

    /**
     * Handle subscription termination.
     */
    protected function handleSubscriptionDeleted(WebhookReceived $event): void
    {
        $team = $this->findTeamByStripeCustomer($event->payload['data']['object']['customer']);

        if ($team) {
            $team->update([
                'custom_storage_limit' => config('picstome.personal_team_storage_limit'),
                'monthly_contract_limit' => config('picstome.personal_team_monthly_contract_limit'),
                'monthly_invoice_limit' => config('picstome.personal_team_monthly_invoice_limit'),
            ]);
        }
    }

    /**
     * Handle one-time payment (lifetime purchase) completions.
     */
    protected function handleCheckoutSessionCompleted(WebhookReceived $event): void
    {
        $session = $event->payload['data']['object'];

        if (($session['mode'] ?? '') !== 'payment' || ($session['payment_status'] ?? '') !== 'paid') {
            return;
        }

        $team = $this->findTeamByStripeCustomer($session['customer']);

        if (! $team) {
            return;
        }

        $priceId = $this->getCheckoutPriceId($session['id'], $team);

        $team->update([
            'lifetime_at' => now(),
            'custom_storage_limit' => $this->storageLimitForPrice($priceId),
        ]);
    }

    /**
     * Fetch the price ID from a checkout session's line items.
     */
    protected function getCheckoutPriceId(string $sessionId, Team $team): ?string
    {
        $lineItems = $team->stripe()->checkout->sessions->allLineItems($sessionId, ['limit' => 1]);

        return $lineItems->data[0]->price->id ?? null;
    }

    /**
     * Determine the storage limit from the webhook payload based on the price ID.
     */
    protected function storageLimitFromPayload(array $payload): int
    {
        $priceId = $payload['data']['object']['items']['data'][0]['price']['id'] ?? null;

        return $this->storageLimitForPrice($priceId);
    }

    /**
     * Look up the storage limit for a price ID, falling back to 1TB.
     */
    protected function storageLimitForPrice(?string $priceId): int
    {
        if ($priceId && isset(config('picstome.storage_limits_by_price')[$priceId])) {
            return config('picstome.storage_limits_by_price')[$priceId];
        }

        return config('picstome.subscription_storage_limit');
    }

    /**
     * Find team by Stripe customer ID.
     */
    protected function findTeamByStripeCustomer(string $customerId): ?Team
    {
        return Team::where('stripe_id', $customerId)->first();
    }
}
