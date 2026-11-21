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
class InvoicePlanLimitService extends MonthlyPlanLimitService
{
    /**
     * Number of invoices issued by the team during the current month.
     */
    public function issuedThisMonth(Team $team): int
    {
        $timezone = $team->invoiceSetting?->timezone ?? config('app.timezone');

        // Timestamps are stored in the application timezone, so the range of
        // the team's month is converted to it before querying.
        $start = now($timezone)->startOfMonth()->setTimezone(config('app.timezone'));
        $end = now($timezone)->endOfMonth()->setTimezone(config('app.timezone'));

        return $team->invoices()
            ->whereNotNull('issued_at')
            ->whereNull('rectifies_invoice_id')
            ->whereBetween('issued_at', [$start, $end])
            ->count();
    }

    public function countThisMonth(Team $team): int
    {
        return $this->issuedThisMonth($team);
    }

    protected function limitField(): string
    {
        return 'monthly_invoice_limit';
    }

    /**
     * Whether the team can issue another invoice.
     */
    public function canIssue(Team $team): bool
    {
        return $this->canUse($team);
    }
}
