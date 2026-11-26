<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Team;
use Illuminate\Support\Collection;

/**
 * Queries behind the finance overview and the dashboard cards.
 *
 * Keeping them here avoids the same "unpaid invoices" and "pending bookings"
 * definitions drifting between the dashboard and the finance page.
 */
class FinanceOverviewService
{
    /**
     * Issued invoices with money pending, oldest due first.
     *
     * The rows are loaded before the paid ones are dropped because the amount
     * settled by the linked payments is not a column we can filter on. The
     * query is bounded so a long-lived team cannot load an unbounded list.
     *
     * @return Collection<int, Invoice>
     */
    public function unpaidInvoices(Team $team, int $limit = 200): Collection
    {
        return $team->invoices()
            ->where('status', Invoice::STATUS_ISSUED)
            ->whereNull('paid_at')
            ->with(['customer', 'payments'])
            ->orderByRaw('coalesce(due_date, issue_date) asc')
            ->orderBy('id')
            ->limit(200)
            ->get()
            ->reject(fn (Invoice $invoice) => $invoice->isPaid())
            ->take($limit)
            ->values();
    }

    public function draftInvoicesCount(Team $team): int
    {
        return $team->invoices()
            ->where('status', Invoice::STATUS_DRAFT)
            ->count();
    }

    /**
     * Bookings waiting for a card payment or a Bizum confirmation.
     *
     * @return Collection<int, Booking>
     */
    public function pendingBookings(Team $team, int $limit = 200): Collection
    {
        return $team->bookings()
            ->with('product')
            ->whereIn('status', [
                BookingStatus::PendingPayment->value,
                BookingStatus::PendingBizum->value,
            ])
            ->where('starts_at', '>=', now())
            ->orderBy('starts_at')
            ->limit($limit)
            ->get();
    }

    /**
     * @return Collection<int, Payment>
     */
    public function recentPayments(Team $team, int $limit = 8): Collection
    {
        return $team->payments()
            ->with(['booking', 'invoice'])
            ->whereNotNull('completed_at')
            ->latest('completed_at')
            ->limit($limit)
            ->get();
    }
}
