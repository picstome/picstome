<?php

namespace App\Services\Booking;

use App\Enums\BookingStatus;
use App\Enums\PaymentKind;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\BookingSetting;
use App\Models\InvoiceSetting;
use App\Models\Payment;
use App\Models\Photoshoot;
use App\Notifications\BookingConfirmed;
use App\Notifications\BookingPaid;
use App\Notifications\BookingPendingBizum;
use App\Notifications\BookingPendingBizumForPhotographer;
use App\Services\BizumReference;
use App\Services\InvoiceFromPaymentService;
use App\Services\InvoiceIssuanceService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Turns a paid booking into everything the photographer expects: a confirmed
 * session, a payment, a photoshoot, a draft invoice and the emails.
 *
 * Every entry point (Stripe webhook, success page, fake payments) calls the
 * same method, which is idempotent per payment kind: a booking is never
 * confirmed twice and a payment is never duplicated.
 */
class BookingFulfilmentService
{
    public function __construct(private readonly InvoiceFromPaymentService $invoices) {}

    /**
     * Record the money and confirm the booking.
     */
    public function pay(
        Booking $booking,
        PaymentKind $kind,
        PaymentMethod $method,
        ?string $stripePaymentIntentId = null,
        ?string $customerEmail = null,
    ): Payment {
        $payment = DB::transaction(function () use ($booking, $kind, $method, $stripePaymentIntentId, $customerEmail) {
            $booking = Booking::query()->whereKey($booking->id)->lockForUpdate()->firstOrFail();

            $existing = $booking->payments()
                ->where('payment_kind', $kind->value)
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            $amount = $kind === PaymentKind::Deposit
                ? $booking->deposit_amount
                : $booking->total_amount;

            $payment = $booking->team->payments()->create([
                'booking_id' => $booking->id,
                'amount' => $amount,
                'currency' => $booking->currency,
                'description' => $booking->product_name
                    .' · '.$booking->starts_at->setTimezone($booking->timezone)->translatedFormat('j M Y')
                    .' · '.match ($kind) {
                        PaymentKind::Deposit => __('Deposit'),
                        PaymentKind::Balance => __('Remaining balance'),
                        PaymentKind::Full => __('Full payment'),
                    },
                'customer_email' => $customerEmail ?? $booking->customer_email,
                'stripe_payment_intent_id' => $stripePaymentIntentId,
                'completed_at' => now(),
                'payment_kind' => $kind,
                'payment_method' => $method,
                'status' => PaymentStatus::Completed,
            ]);

            $photoshoot = $booking->photoshoot ?? $this->createPhotoshoot($booking);

            $booking->update([
                'status' => BookingStatus::Confirmed,
                'payment_method' => $method,
                'payment_kind' => $kind,
                'confirmed_at' => $booking->confirmed_at ?? now(),
                'hold_expires_at' => null,
                'photoshoot_id' => $photoshoot->id,
            ]);

            return $payment;
        });

        $this->createInvoiceDraft($payment);

        $this->notifyConfirmed($booking->fresh());

        return $payment;
    }

    /**
     * The client chose Bizum: the slot stays held while the photographer
     * confirms the transfer by hand.
     */
    public function markPendingBizum(Booking $booking, PaymentKind $kind): Booking
    {
        $booking->update([
            'status' => BookingStatus::PendingBizum,
            'payment_method' => PaymentMethod::Bizum,
            'payment_kind' => $kind,
            'bizum_reference' => $booking->bizum_reference
                ?: app(BizumReference::class)->forBooking($booking),
            'hold_expires_at' => now()->addHours(config('bookings.bizum_expiry_hours')),
        ]);

        Notification::route('mail', $booking->customer_email)
            ->notify(new BookingPendingBizum($booking));

        $owner = $booking->team->owner;

        if ($owner === null) {
            Log::warning('Pending booking has no owner to notify', ['booking' => $booking->ulid]);
        } else {
            $owner->notify(new BookingPendingBizumForPhotographer($booking));
        }

        return $booking;
    }

    /**
     * The job that galleries, contracts and invoices attach to.
     */
    private function createPhotoshoot(Booking $booking): Photoshoot
    {
        $startsAt = $booking->starts_at->setTimezone($booking->timezone);
        $endsAt = $booking->ends_at->setTimezone($booking->timezone);

        return $booking->team->photoshoots()->create([
            'name' => $booking->product_name,
            'date' => $startsAt->toDateString(),
            'customer_name' => $booking->customer_name,
            'customer_email' => $booking->customer_email,
            'customer_id' => $booking->customer_id,
            'location' => $booking->product?->location_name,
            'comment' => __('Booked time: :range', [
                'range' => $startsAt->format('H:i').' - '.$endsAt->format('H:i'),
            ]),
        ]);
    }

    /**
     * A draft invoice is ready for review; issuing it stays a human decision.
     */
    private function createInvoiceDraft(Payment $payment): void
    {
        try {
            if ($payment->invoice()->exists()) {
                return;
            }

            $invoice = $this->invoices->createFromPayment($payment);

            if ($this->shouldAutoIssue($payment)) {
                app(InvoiceIssuanceService::class)->issue($invoice);
            }
        } catch (\Throwable $exception) {
            Log::warning('Booking invoice draft could not be created', [
                'payment_id' => $payment->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Only when the photographer opted in and their fiscal data is complete:
     * issuing with missing data would produce a legally incomplete invoice.
     */
    private function shouldAutoIssue(Payment $payment): bool
    {
        $settings = BookingSetting::forTeam($payment->team);

        if (! $settings->auto_issue_invoices) {
            return false;
        }

        return InvoiceSetting::firstOrCreate(
            ['team_id' => $payment->team_id],
            config('invoices.defaults'),
        )->hasCompleteIssuerData();
    }

    private function notifyConfirmed(Booking $booking): void
    {
        Notification::route('mail', $booking->customer_email)
            ->notify(new BookingConfirmed($booking));

        $owner = $booking->team->owner;

        if ($owner === null) {
            Log::warning('Booking has no owner to notify', ['booking' => $booking->ulid]);

            return;
        }

        $owner->notify(new BookingPaid($booking));
    }
}
