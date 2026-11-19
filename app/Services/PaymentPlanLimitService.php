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
class PaymentPlanLimitService extends MonthlyPlanLimitService
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

    public function countThisMonth(Team $team): int
    {
        return $this->receivedThisMonth($team);
    }

    protected function limitField(): string
    {
        return 'monthly_payment_limit';
    }

    /**
     * Whether the team can receive another payment.
     */
    public function canReceive(Team $team): bool
    {
        return $this->canUse($team);
    }

    /**
     * Payments the team may still receive this month, or null when unlimited.
     */
    public function remainingThisMonth(Team $team): ?int
    {
        return $this->remaining($team);
    }
}
