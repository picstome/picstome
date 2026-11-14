<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Number;
use Laravel\Cashier\Cashier;
use RuntimeException;

/**
 * Resolves subscription plans and their Stripe prices.
 *
 * Product/pricing data lives in Stripe. This service only keeps the display
 * values from config and resolves the current mode's price ID at checkout.
 */
class PlanCatalog
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public function tiers(): array
    {
        return config('plans.tiers', []);
    }

    /**
     * @return array<string, mixed>
     */
    public function tier(string $tier): array
    {
        $tierConfig = config("plans.tiers.{$tier}");

        if (! is_array($tierConfig)) {
            throw new RuntimeException("Unknown subscription tier [{$tier}].");
        }

        return $tierConfig;
    }

    public function priceCents(string $tier, string $interval): int
    {
        $price = $this->tier($tier)['prices'][$interval] ?? null;

        if (! is_int($price)) {
            throw new RuntimeException("Missing price for [{$tier}] [{$interval}].");
        }

        return $price;
    }

    /**
     * Resolve the Stripe price ID for the current mode.
     *
     * A configured price ID wins (useful in tests and for explicit per-mode
     * overrides). Otherwise the price is resolved by its lookup key, which is
     * the same in test and live mode.
     */
    public function priceId(string $tier, string $interval): string
    {
        $configured = $this->tier($tier)['price_ids'][$interval] ?? null;

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        $lookupKey = $this->lookupKey($tier, $interval);

        return Cache::remember(
            "stripe-price-id:{$lookupKey}",
            now()->addHour(),
            function () use ($lookupKey): string {
                $prices = Cashier::stripe()->prices->all([
                    'lookup_keys' => [$lookupKey],
                    'active' => true,
                    'limit' => 1,
                ]);

                $price = $prices->data[0] ?? null;

                if (! $price) {
                    throw new RuntimeException("Stripe price not found for lookup key [{$lookupKey}].");
                }

                return $price->id;
            },
        );
    }

    public function lookupKey(string $tier, string $interval): string
    {
        $lookupKey = $this->tier($tier)['lookup_keys'][$interval] ?? null;

        if (! is_string($lookupKey) || $lookupKey === '') {
            throw new RuntimeException("Missing Stripe lookup key for [{$tier}] [{$interval}].");
        }

        return $lookupKey;
    }

    public function annualPriceAfterDiscountCents(string $tier): int
    {
        $percent = (int) config('promotions.black_friday.annual_discount_percent', 20);

        return (int) round($this->priceCents($tier, 'annual') * (100 - $percent) / 100);
    }

    public function lifetimePriceAfterDiscountCents(string $tier): int
    {
        $percent = (int) config('promotions.black_friday.annual_discount_percent', 20);

        return (int) round($this->priceCents($tier, 'lifetime') * (100 - $percent) / 100);
    }

    public function firstMonthPriceCents(string $tier): int
    {
        $configured = config("promotions.black_friday.first_month_prices.{$tier}");

        return is_int($configured)
            ? $configured
            : $this->priceCents($tier, 'monthly');
    }

    public function monthlyCouponAmountCents(string $tier): int
    {
        return max(0, $this->priceCents($tier, 'monthly') - $this->firstMonthPriceCents($tier));
    }

    public function annualMonthlyEquivalentCents(string $tier): int
    {
        return intdiv($this->priceCents($tier, 'annual'), 12);
    }

    public function annualSavingsCents(string $tier): int
    {
        return max(0, ($this->priceCents($tier, 'monthly') * 12) - $this->priceCents($tier, 'annual'));
    }

    public function annualSavingsMonths(string $tier): float
    {
        $monthly = $this->priceCents($tier, 'monthly');

        return $monthly > 0
            ? round($this->annualSavingsCents($tier) / $monthly, 1)
            : 0.0;
    }

    public function formatCents(int $cents): string
    {
        return Number::currency($cents / 100, config('plans.currency', 'EUR'), app()->getLocale());
    }

    public function tierForStorageLimit(?int $storageLimit): ?string
    {
        if ($storageLimit === null) {
            return null;
        }

        foreach ($this->tiers() as $key => $tier) {
            if ((int) ($tier['storage_limit'] ?? 0) === $storageLimit) {
                return $key;
            }
        }

        return null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function displayTiers(): array
    {
        $tiers = [];

        foreach ($this->tiers() as $key => $tier) {
            $tiers[] = [
                'key' => $key,
                'name' => $tier['name'],
                'photos' => $tier['photos'],
                'highlighted' => (bool) ($tier['highlighted'] ?? false),
                'monthly' => $this->priceDisplay($key, 'monthly'),
                'annual' => $this->priceDisplay($key, 'annual'),
                'lifetime' => $this->priceDisplay($key, 'lifetime'),
                'first_month_cents' => $this->firstMonthPriceCents($key),
                'first_month' => $this->formatCents($this->firstMonthPriceCents($key)),
                'monthly_coupon_cents' => $this->monthlyCouponAmountCents($key),
                'annual_promo_cents' => $this->annualPriceAfterDiscountCents($key),
                'annual_promo' => $this->formatCents($this->annualPriceAfterDiscountCents($key)),
                'lifetime_promo_cents' => $this->lifetimePriceAfterDiscountCents($key),
                'lifetime_promo' => $this->formatCents($this->lifetimePriceAfterDiscountCents($key)),
                'annual_monthly_equivalent_cents' => $this->annualMonthlyEquivalentCents($key),
                'annual_savings_cents' => $this->annualSavingsCents($key),
                'annual_savings_months' => $this->annualSavingsMonths($key),
            ];
        }

        return $tiers;
    }

    /**
     * @return array{cents: int, formatted: string, monthly_equivalent: string|null}
     */
    private function priceDisplay(string $tier, string $interval): array
    {
        $cents = $this->priceCents($tier, $interval);

        return [
            'cents' => $cents,
            'formatted' => $this->formatCents($cents),
            'monthly_equivalent' => $interval === 'annual'
                ? $this->formatCents($this->annualMonthlyEquivalentCents($tier))
                : null,
        ];
    }
}
