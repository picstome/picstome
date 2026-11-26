<?php

namespace App\Services\Booking;

use App\Models\BookingSetting;
use App\Models\Team;
use App\Services\FakePaymentService;

/**
 * Single place that decides whether a team can take bookings.
 *
 * Used by the public profile (to show or hide the entry point) and by the
 * booking page (to return 404), so the button and the page can never disagree.
 */
class BookingPageGate
{
    public function canBook(Team $team): bool
    {
        if (! config('picstome.bookings_feature_enabled', true)) {
            return false;
        }

        if (! $team->owner?->canSeeBookingsFeature()) {
            return false;
        }

        if (! $team->products()->active()->exists()) {
            return false;
        }

        if (! app(BookingPlanLimitService::class)->canReceive($team)) {
            return false;
        }

        $settings = BookingSetting::forTeam($team);

        return $this->canPayWithCard($team, $settings) || $this->canPayWithBizum($settings);
    }

    public function canPayWithCard(Team $team, BookingSetting $settings): bool
    {
        if (! $settings->card_enabled) {
            return false;
        }

        if (FakePaymentService::enabled()) {
            return true;
        }

        $onboarded = $team->stripe_test_mode
            ? $team->stripe_test_onboarded
            : $team->stripe_onboarded;

        return (bool) $onboarded;
    }

    public function canPayWithBizum(BookingSetting $settings): bool
    {
        return $settings->bizum_enabled && filled($settings->bizum_phone);
    }
}
