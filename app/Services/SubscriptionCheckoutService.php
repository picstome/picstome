<?php

namespace App\Services;

use App\Models\Team;
use InvalidArgumentException;
use Laravel\Cashier\Checkout;

/**
 * Creates Stripe Checkout sessions for subscription and lifetime purchases.
 *
 * Coupons are attached server-side so the customer never has to type a code.
 * The same service is used by the authenticated page and the public campaign
 * page, which keeps the checkout payload consistent.
 */
class SubscriptionCheckoutService
{
    public function __construct(
        private readonly PlanCatalog $catalog,
        private readonly BlackFridayPromotion $promotion,
    ) {}

    public function checkout(Team $team, string $tier, string $interval): Checkout
    {
        if (! $this->promotion->canCheckout($team, $tier, $interval)) {
            throw new InvalidArgumentException('This subscription offer is not available for the current team.');
        }

        $offer = $this->promotion->offerFor($team);
        $coupon = $this->promotion->couponFor($tier, $interval, $offer);

        if ($this->promotion->isActive() && $coupon === null) {
            throw new InvalidArgumentException('The promo coupon is not configured.');
        }

        $builder = $team->newSubscription('default', $this->catalog->priceId($tier, $interval));

        if ($coupon !== null) {
            $builder->withCoupon($coupon);
        }

        return $builder
            ->withMetadata(array_filter([
                'tier' => $tier,
                'interval' => $interval,
                'offer' => $offer ?? 'regular',
                'coupon' => $coupon,
                'promotion' => $this->promotion->isActive() ? 'black_friday_2026' : null,
            ], fn ($value) => $value !== null))
            ->checkout($this->sessionOptions($team, $tier, $interval, $coupon));
    }

    public function checkoutLifetime(Team $team, string $tier): Checkout
    {
        if ($this->promotion->stateFor($team) !== 'free') {
            throw new InvalidArgumentException('This lifetime offer is not available for the current team.');
        }

        $coupon = $this->promotion->isActive()
            ? $this->promotion->annualCoupon()
            : null;

        if ($this->promotion->isActive() && $coupon === null) {
            throw new InvalidArgumentException('The promo coupon is not configured.');
        }

        $billable = $coupon ? $team->withCoupon($coupon) : $team;

        return $billable->checkout([
            $this->catalog->priceId($tier, 'lifetime') => 1,
        ], [
            ...$this->sessionOptions($team, $tier, 'lifetime', $coupon),
            'metadata' => array_filter([
                'tier' => $tier,
                'interval' => 'lifetime',
                'offer' => 'regular',
                'coupon' => $coupon,
                'promotion' => $this->promotion->isActive() ? 'black_friday_2026' : null,
            ], fn ($value) => $value !== null),
        ]);
    }

    /**
     * Verify a Stripe Checkout session and build the GA4 purchase payload.
     *
     * The success URL is public, so we only trust sessions that belong to the
     * current team and have been paid. Returns null when there is nothing
     * safe to report.
     *
     * @return array<string, mixed>|null
     */
    public function receipt(Team $team, string $sessionId): ?array
    {
        try {
            $session = $team->stripe()->checkout->sessions->retrieve($sessionId, []);
        } catch (Throwable) {
            return null;
        }

        if ((string) ($session->client_reference_id ?? '') !== (string) $team->id) {
            return null;
        }

        if (! in_array($session->payment_status ?? null, ['paid', 'no_payment_required'], true)) {
            return null;
        }

        $value = round(((int) ($session->amount_total ?? 0)) / 100, 2);
        $tier = (string) ($session->metadata['tier'] ?? '');
        $interval = (string) ($session->metadata['interval'] ?? '');

        return [
            'transaction_id' => (string) $session->id,
            'value' => $value,
            'currency' => strtoupper((string) ($session->currency ?? 'eur')),
            'items' => [[
                'item_id' => $tier !== '' ? $tier : 'plan',
                'item_name' => $tier !== ''
                    ? (string) config("plans.tiers.{$tier}.name", $tier)
                    : 'Picstome',
                'item_variant' => $interval !== '' ? $interval : null,
                'price' => $value,
                'quantity' => 1,
            ]],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function sessionOptions(Team $team, string $tier, string $interval, ?string $coupon): array
    {
        $promoMessage = $this->promotion->isActive()
            ? __('Your Black Friday offer is applied automatically.')
            : __('Secure payment with Stripe. Cancel anytime.');

        return [
            'success_url' => route('subscribe').'?checkout=success&session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => route('subscribe', ['checkout' => 'cancelled']),
            'locale' => app()->getLocale(),
            'client_reference_id' => (string) $team->id,
            'custom_text' => [
                'submit' => ['message' => $promoMessage],
            ],
            'metadata' => array_filter([
                'tier' => $tier,
                'interval' => $interval,
                'coupon' => $coupon,
                'promotion' => $this->promotion->isActive() ? 'black_friday_2026' : null,
            ], fn ($value) => $value !== null),
        ];
    }
}
