<?php

namespace App\Listeners;

use App\Models\Subscription;
use Carbon\Carbon;
use Laravel\Cashier\Events\WebhookHandled;

/**
 * Cashier dispatches WebhookReceived before it persists the subscription, so
 * the local interval must be written after Cashier has handled the event.
 */
class SyncSubscriptionBillingInterval
{
    public function handle(WebhookHandled $event): void
    {
        $type = $event->payload['type'] ?? null;

        if (! in_array($type, ['customer.subscription.created', 'customer.subscription.updated'], true)) {
            return;
        }

        $data = $event->payload['data']['object'] ?? [];
        $stripeId = $data['id'] ?? null;

        if (! $stripeId) {
            return;
        }

        $subscription = Subscription::query()->where('stripe_id', $stripeId)->first();

        if (! $subscription) {
            return;
        }

        $interval = $data['items']['data'][0]['price']['recurring']['interval'] ?? null;

        $updates = ['billing_interval' => $interval];

        $periodEnd = $data['current_period_end']
            ?? $data['items']['data'][0]['current_period_end']
            ?? null;

        if ($periodEnd) {
            $updates['current_period_end'] = Carbon::createFromTimestamp($periodEnd);
        }

        if ($interval === 'year') {
            $updates += [
                'stripe_schedule_id' => null,
                'scheduled_tier' => null,
                'scheduled_price_id' => null,
                'scheduled_change_at' => null,
            ];
        }

        $subscription->forceFill($updates)->save();
    }
}
