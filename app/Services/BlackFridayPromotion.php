<?php

namespace App\Services;

use App\Models\Team;
use Carbon\Carbon;
use Illuminate\Support\Carbon as IlluminateCarbon;

/**
 * Campaign rules for the Black Friday promotion.
 *
 * Keeping the rules here (instead of in Blade) means the banner, pricing page
 * and checkout all agree on who is eligible and which coupon applies.
 */
class BlackFridayPromotion
{
    public function isActive(): bool
    {
        $config = config('promotions.black_friday', []);

        if (! ($config['enabled'] ?? false)) {
            return false;
        }

        // Previewing the campaign before its public start date is only allowed
        // locally. Production always follows the configured window.
        if (($config['force'] ?? false) && app()->environment(['local', 'testing'])) {
            return true;
        }

        $timezone = $config['timezone'] ?? config('app.timezone', 'UTC');
        $now = now($timezone);

        return $now->greaterThanOrEqualTo($this->startsAt())
            && $now->lessThan($this->endsAt());
    }

    public function startsAt(): Carbon
    {
        $config = config('promotions.black_friday', []);
        $timezone = $config['timezone'] ?? config('app.timezone', 'UTC');

        return Carbon::parse($config['starts_at'] ?? '2026-11-16 00:00:00', $timezone);
    }

    public function endsAt(): Carbon
    {
        $config = config('promotions.black_friday', []);
        $timezone = $config['timezone'] ?? config('app.timezone', 'UTC');

        return Carbon::parse($config['ends_at'] ?? '2026-12-01 00:00:00', $timezone);
    }

    public function annualDiscountPercent(): int
    {
        return (int) config('promotions.black_friday.annual_discount_percent', 20);
    }

    public function stateFor(?Team $team): string
    {
        if (! $team) {
            return 'guest';
        }

        if ($team->lifetime_at !== null) {
            return 'lifetime';
        }

        if (! $team->subscribed()) {
            $hasProblemSubscription = $team->subscriptions()
                ->whereIn('stripe_status', ['incomplete', 'past_due', 'unpaid', 'paused'])
                ->where(function ($query) {
                    $query->whereNull('ends_at')
                        ->orWhere('ends_at', '>', now());
                })
                ->exists();

            return $hasProblemSubscription ? 'billing' : 'free';
        }

        $subscription = $team->subscription('default');

        if (! $subscription?->valid()) {
            return 'billing';
        }

        if (in_array($subscription->stripe_status, ['past_due', 'unpaid', 'paused', 'incomplete'], true)) {
            return 'billing';
        }

        return match ($subscription->billing_interval) {
            'month' => 'monthly',
            'year' => 'annual',
            default => 'billing',
        };
    }

    public function offerFor(?Team $team): ?string
    {
        if (! $this->isActive()) {
            return null;
        }

        return match ($this->stateFor($team)) {
            'guest', 'free' => 'free',
            'monthly' => 'monthly',
            default => null,
        };
    }

    public function canCheckout(?Team $team, string $tier, string $interval): bool
    {
        if (! in_array($interval, ['monthly', 'annual'], true)) {
            return false;
        }

        if (! array_key_exists($tier, config('plans.tiers', []))) {
            return false;
        }

        return $this->stateFor($team) === 'free';
    }

    public function canScheduleUpgrade(?Team $team, string $tier): bool
    {
        if (! $this->isActive() || $this->stateFor($team) !== 'monthly') {
            return false;
        }

        if (! array_key_exists($tier, config('plans.tiers', []))) {
            return false;
        }

        if ($this->annualCoupon() === null) {
            return false;
        }

        $subscription = $team?->subscription('default');

        return $subscription !== null
            && $subscription->stripe_status === 'active'
            && $subscription->ends_at === null
            && $subscription->stripe_schedule_id === null;
    }

    public function couponFor(string $tier, string $interval, ?string $offer): ?string
    {
        if ($interval === 'monthly') {
            return $offer === 'free'
                ? $this->monthlyCouponFor($tier)
                : null;
        }

        if ($interval === 'annual' && in_array($offer, ['free', 'monthly'], true)) {
            return $this->annualCoupon();
        }

        return null;
    }

    public function monthlyCouponFor(string $tier): ?string
    {
        $coupon = config("promotions.black_friday.coupons.monthly.{$tier}");

        return is_string($coupon) && $coupon !== '' ? $coupon : null;
    }

    public function annualCoupon(): ?string
    {
        $coupon = config('promotions.black_friday.coupons.annual');

        return is_string($coupon) && $coupon !== '' ? $coupon : null;
    }

    public function firstMonthPriceCents(string $tier): int
    {
        $configured = config("promotions.black_friday.first_month_prices.{$tier}");

        if (is_int($configured)) {
            return $configured;
        }

        return (int) config("plans.tiers.{$tier}.prices.monthly", 0);
    }

    public function offerEndsAtLabel(): string
    {
        return $this->endsAt()
            ->timezone(config('promotions.black_friday.timezone', config('app.timezone', 'UTC')))
            ->isoFormat('LL');
    }

    public function offerStartsAtLabel(): string
    {
        return $this->startsAt()
            ->timezone(config('promotions.black_friday.timezone', config('app.timezone', 'UTC')))
            ->isoFormat('LL');
    }

    public function daysRemaining(): int
    {
        return max(0, (int) now(config('promotions.black_friday.timezone', config('app.timezone')))
            ->diffInDays($this->endsAt(), false));
    }

    public function endsAtForDisplay(): IlluminateCarbon
    {
        return $this->endsAt()->timezone(config('app.timezone', 'UTC'));
    }
}
