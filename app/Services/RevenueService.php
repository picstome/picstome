<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Team;
use Carbon\CarbonInterface;

/**
 * Revenue collected by a team in a period.
 *
 * A photographer gets paid through Stripe or settles the invoice by hand, so
 * the revenue adds up both sources:
 *
 * - the payments completed in the period (stored in cents), and
 * - the invoices marked as paid in the period that no payment settles, which
 *   is what the app records when the team marks an invoice as paid manually.
 *
 * An invoice settled by a payment is never counted twice: the payment is the
 * money that actually entered the account.
 *
 * Amounts are expressed in the currency of the team. Payments are always
 * stored in that currency. An invoice issued in another currency is converted
 * with its exchange rate when the team is billed in euros, and skipped
 * otherwise, since there is no rate to convert it with.
 */
class RevenueService
{
    /**
     * Revenue collected during the last N days.
     */
    public function lastDays(Team $team, int $days = 30): float
    {
        return $this->between($team, now()->subDays($days), now());
    }

    /**
     * Revenue collected between two moments, both included.
     */
    public function between(Team $team, CarbonInterface $start, CarbonInterface $end): float
    {
        $currency = strtoupper((string) ($team->stripe_currency ?: 'EUR'));

        $payments = (float) $team->payments()
            ->whereBetween('completed_at', [$start, $end])
            ->sum('amount') / 100;

        $invoices = $team->invoices()
            ->whereNotNull('paid_at')
            ->whereBetween('paid_at', [$start, $end])
            ->where('status', Invoice::STATUS_ISSUED)
            ->whereDoesntHave('payments', fn ($query) => $query->whereNotNull('completed_at'))
            ->get(['currency', 'total', 'exchange_rate'])
            ->sum(fn (Invoice $invoice) => $this->inTeamCurrency($invoice, $currency));

        return round($payments + $invoices, 2);
    }

    /**
     * Amount of the invoice in the currency of the team, or zero when it
     * cannot be converted.
     */
    private function inTeamCurrency(Invoice $invoice, string $currency): float
    {
        $amount = (float) $invoice->total;
        $invoiceCurrency = strtoupper((string) $invoice->currency);

        if ($invoiceCurrency === $currency) {
            return $amount;
        }

        $rate = (float) $invoice->exchange_rate;

        if ($rate <= 0) {
            return 0.0;
        }

        // Invoices store the rate to euros, so it is only usable when the team
        // is billed in euros.
        if ($currency === 'EUR') {
            return round($amount * $rate, 2);
        }

        if ($invoiceCurrency === 'EUR') {
            return round($amount / $rate, 2);
        }

        return 0.0;
    }
}
