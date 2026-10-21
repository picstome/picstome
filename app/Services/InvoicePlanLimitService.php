<?php

namespace App\Services;

use App\Models\Team;

/**
 * Plan limits applied to the invoices of a team.
 *
 * Free teams can issue a limited number of invoices per month. Rectificative
 * invoices never consume the limit, so a free team can always correct the
 * invoice it already issued.
 */
class InvoicePlanLimitService
{
    /**
     * Number of invoices issued by the team during the current month.
     */
    public function issuedThisMonth(Team $team): int
    {
        $timezone = $team->invoiceSetting?->timezone ?? config('app.timezone');

        $start = now($timezone)->startOfMonth();
        $end = now($timezone)->endOfMonth();

        return $team->invoices()
            ->whereNotNull('issued_at')
            ->whereNull('rectifies_invoice_id')
            ->whereBetween('issued_at', [$start, $end])
            ->count();
    }

    /**
     * Whether the team can issue another invoice.
     */
    public function canIssue(Team $team): bool
    {
        if ($team->subscribed()) {
            return true;
        }

        $limit = (int) config('picstome.personal_team_monthly_invoice_limit');

        if ($limit <= 0) {
            return false;
        }

        return $this->issuedThisMonth($team) < $limit;
    }
}
