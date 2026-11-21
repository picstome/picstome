<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Team;

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
        $start = now()->subDays($days);
        $currency = strtoupper((string) ($team->stripe_currency ?: 'EUR'));

        $payments = (float) $team->payments()
            ->where('completed_at', '>=', $start)
            ->sum('amount') / 100;

        $invoices = $team->invoices()
            ->whereNotNull('paid_at')
            ->where('paid_at', '>=', $start)
            ->where('status', Invoice::STATUS_ISSUED)
            ->whereDoesntHave('payments', fn ($query) => $query->whereNotNull('completed_at'))
            ->get(['currency', 'total', 'exchange_rate'])
            ->sum(fn (Invoice $invoice) => $this->inTeamCurrency($invoice, $currency));

        return round($payments + $invoices + $this->paidRectifiedInvoices($team, $start, $currency), 2);
    }

    /**
     * Revenue of the invoices that were marked as paid and then rectified.
     *
     * Rectifying an invoice replaces its amount with the corrective one, but the
     * team usually keeps the paid flag on the original, which is the invoice the
     * client actually paid. In that case the money counts once, with the amount
     * of the invoice that is in force now (the last issued corrective of the
     * chain). When any corrective of the chain is marked as paid itself, or is
     * settled by a payment, it is already counted on its own and the original
     * is skipped.
     */
    private function paidRectifiedInvoices(Team $team, mixed $start, string $currency): float
    {
        return $team->invoices()
            ->whereNotNull('paid_at')
            ->where('paid_at', '>=', $start)
            ->where('status', Invoice::STATUS_RECTIFIED)
            ->whereDoesntHave('payments', fn ($query) => $query->whereNotNull('completed_at'))
            ->get()
            ->sum(function (Invoice $original) use ($currency): float {
                $current = $this->invoiceInForce($original);

                return $current === null ? 0.0 : $this->inTeamCurrency($current, $currency);
            });
    }

    /**
     * Last issued corrective that replaces the invoice, or null when there is
     * none or a corrective of the chain is already paid on its own.
     */
    private function invoiceInForce(Invoice $invoice): ?Invoice
    {
        $current = null;

        while ($corrective = $this->issuedCorrective($invoice)) {
            $settledByPayment = $corrective->payments()->whereNotNull('completed_at')->exists();

            if ($corrective->isMarkedPaid() || $settledByPayment) {
                return null;
            }

            $current = $invoice = $corrective;
        }

        return $current;
    }

    private function issuedCorrective(Invoice $invoice): ?Invoice
    {
        return $invoice->rectifications()
            ->whereIn('status', [Invoice::STATUS_ISSUED, Invoice::STATUS_RECTIFIED])
            ->first();
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
