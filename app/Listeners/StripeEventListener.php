<?php

namespace App\Listeners;

use App\Models\Team;
use Laravel\Cashier\Events\WebhookReceived;

class StripeEventListener
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(WebhookReceived $event): void
    {
        match ($event->payload['type']) {
            'customer.subscription.created' => $this->handleSubscriptionCreated($event),
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
            $this->updateTeamStorageLimit($team, config('picstome.subscription_storage_limit'));
        }
    }

    /**
     * Handle subscription termination.
     */
    protected function handleSubscriptionDeleted(WebhookReceived $event): void
    {
        $team = $this->findTeamByStripeCustomer($event->payload['data']['object']['customer']);

        if ($team) {
            $this->updateTeamStorageLimit($team, config('picstome.personal_team_storage_limit'));
        }
    }

    /**
     * Find team by Stripe customer ID.
     */
    protected function findTeamByStripeCustomer(string $customerId): ?Team
    {
        return Team::where('stripe_id', $customerId)->first();
    }

    /**
     * Update team's storage limit.
     */
    protected function updateTeamStorageLimit(Team $team, int $storageLimit): void
    {
        $team->update(['custom_storage_limit' => $storageLimit]);
    }
}
