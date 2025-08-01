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
        if ($event->payload['type'] === 'invoice.payment_succeeded') {
            $team = Team::where('stripe_id', $event->payload['data']['object']['customer'])->first();

            if ($team) {
                $team->update(['custom_storage_limit' => null]);
            }
        }
    }
}
