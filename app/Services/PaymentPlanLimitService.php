<?php

namespace App\Services;

use App\Models\Team;

/**
 * Plan limits applied to the payments received by a team.
 *
 * Free teams can receive a limited number of payments per month. Only
 * completed payments consume the limit: opening a checkout and abandoning it
 * must not use up the month, because nothing was charged.
 */
class PaymentPlanLimitService
{
    /**
     * Number of payments completed by the team during the current month.
     */
    public function receivedThisMonth(Team $team): int
    {
        $start = now()->startOfMonth();
        $end = now()->endOfMonth();

        return $team->payments()
            ->whereNotNull('completed_at')
            ->whereBetween('completed_at', [$start, $end])
            ->count();
    }

    /**
     * Whether the team can receive another payment.
     *
     * A null limit means unlimited, mirroring the monthly invoice limit.
     * Paid and lifetime teams are never limited.
     */
    public function canReceive(Team $team): bool
    {
        if ($team->subscribed() || is_null($team->monthly_payment_limit)) {
            return true;
        }

        if ($team->monthly_payment_limit <= 0) {
            return false;
        }

        return $this->receivedThisMonth($team) < $team->monthly_payment_limit;
    }

    /**
     * Payments the team may still receive this month, or null when unlimited.
     */
    public function remainingThisMonth(Team $team): ?int
    {
        if ($team->subscribed() || is_null($team->monthly_payment_limit)) {
            return null;
        }

        return max(0, $team->monthly_payment_limit - $this->receivedThisMonth($team));
    }
}
