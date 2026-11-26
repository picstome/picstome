<?php

namespace App\Console\Commands;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Services\PaymentLinkService;
use Illuminate\Console\Command;

/**
 * Create agenda entries for payment links paid before the Booking was added.
 *
 * The command is idempotent and only touches completed payments that carry a
 * session date and already have a Photoshoot.
 */
class BackfillPaymentLinkBookings extends Command
{
    protected $signature = 'payments:backfill-link-bookings {--dry-run} {--force}';

    protected $description = 'Create the missing agenda entries for completed payment links with a session date';

    public function handle(PaymentLinkService $service): int
    {
        if (! $this->option('dry-run') && ! $this->option('force')) {
            $this->warn('Use --dry-run to inspect the changes or --force to write them.');

            return self::FAILURE;
        }

        $payments = Payment::query()
            ->where('status', PaymentStatus::Completed->value)
            ->whereNull('booking_id')
            ->whereNotNull('photoshoot_id')
            ->whereNotNull('metadata')
            ->orderBy('id')
            ->get()
            ->filter(fn (Payment $payment): bool => (bool) ($payment->metadata['booking'] ?? false));

        $created = 0;

        foreach ($payments as $payment) {
            if ($this->option('dry-run')) {
                $this->line("Would create a booking for payment {$payment->id} ({$payment->description}).");

                continue;
            }

            if ($service->ensureBooking($payment) !== null) {
                $created++;
                $this->line("Created a booking for payment {$payment->id} ({$payment->description}).");
            }
        }

        $this->info($this->option('dry-run')
            ? "{$payments->count()} payment(s) would be updated."
            : "{$created} booking(s) created.");

        return self::SUCCESS;
    }
}
