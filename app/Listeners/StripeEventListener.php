<?php

namespace App\Listeners;

use App\Jobs\SendGa4Purchase;
use App\Models\Team;
use App\Services\StripeAttributionMetadata;
use Laravel\Cashier\Events\WebhookReceived;
use Throwable;

class StripeEventListener
{
    /**
     * Handle the event.
     */
    public function handle(WebhookReceived $event): void
    {
        match ($event->payload['type']) {
            'checkout.session.completed' => $this->handleCheckoutSessionCompleted($event),
            'invoice.paid' => $this->handleInvoicePaid($event),
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
                'monthly_payment_limit' => null, // Unlimited payments
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
                'monthly_payment_limit' => null, // Unlimited payments
            ]);
        }
    }

    /**
     * Handle subscription termination.
     *
     * Galleries without an expiration date were only allowed while the
     * subscription was active. They get a year from their creation (or a
     * month from now for older galleries, so nothing is deleted overnight),
     * which is the same retention rule free and lifetime plans follow.
     */
    protected function handleSubscriptionDeleted(WebhookReceived $event): void
    {
        $team = $this->findTeamByStripeCustomer($event->payload['data']['object']['customer']);

        if (! $team) {
            return;
        }

        $team->update([
            'custom_storage_limit' => config('picstome.personal_team_storage_limit'),
            'monthly_contract_limit' => config('picstome.personal_team_monthly_contract_limit'),
            'monthly_invoice_limit' => config('picstome.personal_team_monthly_invoice_limit'),
            'monthly_payment_limit' => config('picstome.personal_team_monthly_payment_limit'),
        ]);

        $team->galleries()
            ->whereNull('expiration_date')
            ->where('is_public', false)
            ->chunkById(100, function ($galleries): void {
                foreach ($galleries as $gallery) {
                    $cap = $gallery->created_at->copy()->addYear();

                    $gallery->update([
                        'expiration_date' => $cap->isPast() ? now()->addMonth() : $cap,
                    ]);
                }
            });
    }

    /**
     * Stamp the attribution metadata on every subscription payment.
     *
     * Checkout only allows payment_intent_data in one-time mode, so the first
     * invoice and every renewal are stamped here instead. The subscription
     * metadata is preferred; old subscriptions fall back to the user.
     */
    protected function handleInvoicePaid(WebhookReceived $event): void
    {
        $invoice = $event->payload['data']['object'];
        $team = $this->findTeamByStripeCustomer($invoice['customer'] ?? '');

        if (! $team) {
            return;
        }

        $paymentIntentId = $invoice['payment_intent'] ?? null;

        if (! is_string($paymentIntentId) || $paymentIntentId === '') {
            return;
        }

        $metadata = $this->subscriptionAttributionMetadata($invoice, $team);

        if ($metadata === []) {
            return;
        }

        $this->stampPaymentIntentMetadata($team, $paymentIntentId, $metadata);
    }

    /**
     * @param  array<string, mixed>  $invoice
     * @return array<string, string>
     */
    protected function subscriptionAttributionMetadata(array $invoice, Team $team): array
    {
        $subscriptionId = $invoice['subscription'] ?? null;
        $attribution = app(StripeAttributionMetadata::class);

        if (is_string($subscriptionId) && $subscriptionId !== '') {
            try {
                $subscription = $team->stripe()->subscriptions->retrieve($subscriptionId);
                $metadata = $attribution->fromMetadata($subscription->metadata);

                if ($metadata !== []) {
                    return $metadata;
                }
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        return $attribution->forTeam($team);
    }

    /**
     * @param  array<string, string>  $metadata
     */
    protected function stampPaymentIntentMetadata(Team $team, string $paymentIntentId, array $metadata): void
    {
        try {
            $team->stripe()->paymentIntents->update($paymentIntentId, ['metadata' => $metadata]);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /**
     * Handle one-time payment (lifetime) completions and queue the GA4
     * purchase backup for both subscription and lifetime checkouts.
     */
    protected function handleCheckoutSessionCompleted(WebhookReceived $event): void
    {
        $session = $event->payload['data']['object'];

        $status = $session['payment_status'] ?? null;

        if (! in_array($status, ['paid', 'no_payment_required'], true)) {
            return;
        }

        $team = $this->findTeamByStripeCustomer($session['customer'] ?? '');

        if (! $team) {
            return;
        }

        $this->queueGa4Purchase($session, $team);

        if (($session['mode'] ?? '') !== 'payment') {
            return;
        }

        $priceId = $this->getCheckoutPriceId($session['id'], $team);

        $team->update([
            'lifetime_at' => now(),
            'custom_storage_limit' => $this->storageLimitForPrice($priceId),
        ]);
    }

    /**
     * Send the purchase to GA4 through Measurement Protocol. The client-side
     * event uses the same transaction id, so GA4 keeps only one purchase.
     *
     * @param  array<string, mixed>  $session
     */
    protected function queueGa4Purchase(array $session, Team $team): void
    {
        if (! config('analytics.enabled')) {
            return;
        }

        $user = $team->owner;
        $clientId = $user?->attr_json['ga_client_id'] ?? null;

        if (! $clientId) {
            return;
        }

        $value = round(((int) ($session['amount_total'] ?? 0)) / 100, 2);
        $tier = (string) ($session['metadata']['tier'] ?? '');
        $interval = (string) ($session['metadata']['interval'] ?? '');

        SendGa4Purchase::dispatch([
            'client_id' => (string) $clientId,
            'user_id' => $user ? (string) $user->id : null,
            'transaction_id' => (string) ($session['id'] ?? ''),
            'value' => $value,
            'currency' => strtoupper((string) ($session['currency'] ?? 'eur')),
            'items' => [[
                'item_id' => $tier !== '' ? $tier : 'plan',
                'item_name' => $tier !== ''
                    ? (string) config("plans.tiers.{$tier}.name", $tier)
                    : 'Picstome',
                'item_variant' => $interval !== '' ? $interval : null,
                'price' => $value,
                'quantity' => 1,
            ]],
            'coupon' => $session['metadata']['coupon'] ?? null,
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
        $price = $payload['data']['object']['items']['data'][0]['price'] ?? [];
        $storageLimit = $price['metadata']['storage_limit'] ?? null;

        if (is_numeric($storageLimit)) {
            return (int) $storageLimit;
        }

        $priceId = $price['id'] ?? null;

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
