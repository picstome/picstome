<?php

namespace App\Services\Booking;

use App\Enums\BookingStatus;
use App\Models\BookingSetting;
use App\Models\Team;
use Carbon\CarbonImmutable;

/**
 * Monthly booking allowance of a team.
 *
 * Free plans use the same `monthly_payment_limit` as the payments feature, so
 * a free photographer can receive one booking per month. Null means unlimited
 * (paid and lifetime plans).
 */
class BookingPlanLimitService
{
    public function canReceive(Team $team): bool
    {
        $limit = $team->monthly_payment_limit;

        if ($limit === null) {
            return true;
        }

        return $this->used($team) < (int) $limit;
    }

    public function used(Team $team): int
    {
        $timezone = BookingSetting::forTeam($team)->timezone;
        $now = CarbonImmutable::now($timezone);
        $start = $now->startOfMonth()->utc();
        $end = $now->endOfMonth()->utc();

        return $team->bookings()
            ->where('source', 'public')
            ->whereIn('status', [
                BookingStatus::PendingPayment->value,
                BookingStatus::PendingBizum->value,
                BookingStatus::Confirmed->value,
                BookingStatus::Completed->value,
            ])
            ->whereBetween('created_at', [$start, $end])
            ->count();
    }

    public function remaining(Team $team): ?int
    {
        $limit = $team->monthly_payment_limit;

        return $limit === null ? null : max(0, (int) $limit - $this->used($team));
    }
}
