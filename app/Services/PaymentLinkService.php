<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\PaymentKind;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Photoshoot;
use App\Models\Team;
use App\Notifications\BookingCreated;
use App\Notifications\PaymentLinkBizumConfirmed;
use App\Notifications\PaymentLinkBizumPending;
use App\Notifications\PaymentLinkBizumPendingForPhotographer;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * Payment-link checkout, shared by card and Bizum.
 *
 * Card payments arrive with a confirmed Stripe session. Bizum payments start
 * as pending and are confirmed by the photographer from the payments page.
 * Both paths create the same kind of Payment and a draft invoice, and both
 * keep working when the booking feature flags are disabled.
 */
class PaymentLinkService
{
    public function __construct(
        private readonly InvoiceFromPaymentService $invoices,
        private readonly BizumReference $references,
    ) {}

    /**
     * Record a paid card checkout and create its draft invoice.
     *
     * @param  array<string, mixed>  $session
     */
    public function recordCard(Team $team, array $session): Payment
    {
        $paymentIntentId = $session['payment_intent'] ?? null;

        if (! is_string($paymentIntentId) || $paymentIntentId === '') {
            throw new \InvalidArgumentException('The Stripe session has no payment intent.');
        }

        if ($existing = Payment::query()->where('stripe_payment_intent_id', $paymentIntentId)->first()) {
            $this->ensureBooking($existing);
            $this->createInvoice($existing);

            return $existing;
        }

        $metadata = is_array($session['metadata'] ?? null) ? $session['metadata'] : [];
        $customerDetails = is_array($session['customer_details'] ?? null) ? $session['customer_details'] : [];
        $description = (string) ($session['line_items']['data'][0]['description'] ?? $metadata['description'] ?? __('Payment'));
        $name = $customerDetails['name'] ?? null;
        $email = $customerDetails['email'] ?? null;
        $phone = $customerDetails['phone'] ?? null;

        $customer = $this->customer($team, $name, $email, $phone);
        $photoshoot = $this->photoshoot($team, $metadata, $customer, $description, $name);

        $payment = $team->payments()->create([
            'amount' => $session['amount_total'] ?? 0,
            'currency' => $session['currency'] ?? ($team->stripe_currency ?: 'eur'),
            'description' => $description,
            'stripe_payment_intent_id' => $paymentIntentId,
            'customer_name' => $name,
            'customer_email' => $email,
            'customer_phone' => $phone,
            'completed_at' => now(),
            'photoshoot_id' => $photoshoot?->id,
            'status' => PaymentStatus::Completed,
            'payment_method' => PaymentMethod::Card,
            'metadata' => $metadata,
        ]);

        $booking = $this->booking($payment, $photoshoot, $metadata, $customer);

        $this->createInvoice($payment);

        if ($booking !== null) {
            $this->notifyBookingCreated($payment, $photoshoot, $metadata);
        }

        return $payment;
    }

    /**
     * Start a Bizum transfer for a payment link.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function startBizum(Team $team, array $attributes): Payment
    {
        if (! $team->canAcceptBizumPayments()) {
            throw ValidationException::withMessages([
                'payment' => __('This photographer does not accept Bizum payments.'),
            ]);
        }

        $limit = $team->monthly_payment_limit;

        if ($limit !== null) {
            $pending = $team->payments()
                ->pendingBizum()
                ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
                ->count();

            $remaining = (int) $limit
                - app(PaymentPlanLimitService::class)->receivedThisMonth($team)
                - $pending;

            if ($remaining <= 0) {
                throw ValidationException::withMessages([
                    'payment' => __('This photographer cannot accept more payments this month.'),
                ]);
            }
        }

        $metadata = (array) ($attributes['metadata'] ?? []);
        $timezone = (string) ($metadata['timezone'] ?? config('app.timezone'));
        $date = filled($metadata['booking_date'] ?? null)
            ? Carbon::parse((string) $metadata['booking_date'], $timezone)
            : now($timezone);

        $payment = $team->payments()->create([
            'amount' => (int) $attributes['amount'],
            'currency' => strtoupper((string) ($attributes['currency'] ?? $team->stripe_currency ?: 'eur')),
            'description' => $attributes['description'],
            'customer_name' => $attributes['customer_name'] ?? null,
            'customer_email' => $attributes['customer_email'] ?? null,
            'customer_phone' => $attributes['customer_phone'] ?? null,
            'photoshoot_id' => $attributes['photoshoot_id'] ?? null,
            'status' => PaymentStatus::PendingBizum,
            'payment_method' => PaymentMethod::Bizum,
            'reference' => $this->references->forPayment($team, (string) ($attributes['customer_name'] ?? ''), $date),
            'expires_at' => now()->addHours((int) config('payments.bizum_expiry_hours', 48)),
            'metadata' => $metadata,
        ]);

        $owner = $team->owner;

        if ($owner === null) {
            Log::warning('Pending Bizum payment has no owner to notify', ['payment' => $payment->id]);
        } else {
            $owner->notify(new PaymentLinkBizumPendingForPhotographer($payment));
        }

        if (filled($payment->customer_email)) {
            Notification::route('mail', $payment->customer_email)
                ->notify(new PaymentLinkBizumPending($payment));
        }

        return $payment;
    }

    /**
     * The photographer checks the transfer and confirms it.
     */
    public function confirmBizum(Payment $payment): Payment
    {
        if ($payment->isCompleted()) {
            $this->ensureBooking($payment);
            $this->createInvoice($payment);

            return $payment;
        }

        $payment = DB::transaction(function () use ($payment) {
            $payment = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($payment->isCompleted()) {
                return $payment;
            }

            if (! $payment->isPendingBizum()) {
                throw ValidationException::withMessages([
                    'payment' => __('Only pending Bizum payments can be confirmed.'),
                ]);
            }

            $payment->update([
                'status' => PaymentStatus::Completed,
                'completed_at' => now(),
                'expires_at' => null,
            ]);

            return $payment;
        });

        if ($payment->isCompleted() && $payment->booking_id !== null) {
            return $payment;
        }

        $customer = $this->customer($payment->team, $payment->customer_name, $payment->customer_email, $payment->customer_phone);
        $photoshoot = null;

        if ($payment->photoshoot_id === null && $this->usesBookingMetadata($payment->metadata ?? [])) {
            $photoshoot = $this->photoshoot(
                $payment->team,
                $payment->metadata ?? [],
                $customer,
                $payment->description,
                $payment->customer_name,
            );

            if ($photoshoot !== null) {
                $payment->update(['photoshoot_id' => $photoshoot->id]);
            }
        } elseif ($payment->photoshoot_id !== null) {
            $photoshoot = $payment->photoshoot;
        }

        $booking = $photoshoot === null
            ? null
            : $this->booking($payment, $photoshoot, $payment->metadata ?? [], $customer);

        $this->createInvoice($payment);

        if ($booking !== null) {
            $this->notifyBookingCreated($payment, $photoshoot, $payment->metadata ?? []);
        } else {
            $this->notifyBizumConfirmed($payment);
        }

        return $payment;
    }

    public function cancelBizum(Payment $payment): void
    {
        if (! $payment->isPendingBizum()) {
            throw ValidationException::withMessages([
                'payment' => __('Only pending Bizum payments can be cancelled.'),
            ]);
        }

        $payment->update([
            'status' => PaymentStatus::Cancelled,
            'expires_at' => null,
        ]);
    }

    /**
     * Close the Bizum requests whose window has passed.
     */
    public function expirePendingBizum(): int
    {
        return Payment::query()
            ->pendingBizum()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->update([
                'status' => PaymentStatus::Expired->value,
                'updated_at' => now(),
            ]);
    }

    private function customer(Team $team, ?string $name, ?string $email, ?string $phone = null): Customer
    {
        if (blank($email)) {
            return $team->customers()->create([
                'name' => $name ?: __('Customer'),
                'phone' => $phone,
            ]);
        }

        $customer = $team->customers()->firstOrCreate(
            ['email' => $email],
            ['name' => $name ?: $email, 'phone' => $phone],
        );

        $updates = [];

        if (filled($name) && (blank($customer->name) || $customer->name === $customer->email)) {
            $updates['name'] = $name;
        }

        if (filled($phone) && blank($customer->phone)) {
            $updates['phone'] = $phone;
        }

        if ($updates !== []) {
            $customer->update($updates);
        }

        return $customer;
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function photoshoot(
        Team $team,
        array $metadata,
        ?Customer $customer,
        string $description,
        ?string $customerName,
    ): ?Photoshoot {
        if (filled($metadata['photoshoot_id'] ?? null)) {
            return $team->photoshoots()->find($metadata['photoshoot_id']);
        }

        if (! $this->usesBookingMetadata($metadata)) {
            return null;
        }

        $timezone = (string) ($metadata['timezone'] ?? 'UTC');
        $date = Carbon::parse((string) $metadata['booking_date'], $timezone)->format('Y-m-d');
        $timeRange = __('Booked time: :range', [
            'range' => $metadata['booking_start_time'].' - '.$metadata['booking_end_time'],
        ]);

        return $team->photoshoots()->create([
            'name' => $description,
            'date' => $date,
            'customer_name' => $customer->name ?? $customerName,
            'customer_id' => $customer->id,
            'comment' => $timeRange,
        ]);
    }

    /**
     * Create the agenda entry for an already completed payment link.
     *
     * Used by the backfill command for payments created before payment links
     * started writing a Booking.
     */
    public function ensureBooking(Payment $payment): ?Booking
    {
        if ($payment->booking_id !== null) {
            return $payment->booking;
        }

        $photoshoot = $payment->photoshoot;

        if ($photoshoot === null) {
            return null;
        }

        $customer = $this->customer(
            $payment->team,
            $payment->customer_name,
            $payment->customer_email,
            $payment->customer_phone,
        );

        return $this->booking($payment, $photoshoot, $payment->metadata ?? [], $customer);
    }

    /**
     * A payment link with a session date also creates the agenda entry, so the
     * photographer sees the session in the calendar and the slot is blocked.
     *
     * @param  array<string, mixed>  $metadata
     */
    private function booking(Payment $payment, ?Photoshoot $photoshoot, array $metadata, Customer $customer): ?Booking
    {
        if ($photoshoot === null || ! $this->usesBookingMetadata($metadata) || $payment->booking_id !== null) {
            return $payment->booking;
        }

        if ($existing = Booking::query()
            ->where('team_id', $payment->team_id)
            ->where('photoshoot_id', $photoshoot->id)
            ->first()) {
            $payment->update(['booking_id' => $existing->id]);

            return $existing;
        }

        $timezone = (string) ($metadata['timezone'] ?? 'UTC');
        $startsAt = Carbon::parse(
            $metadata['booking_date'].' '.$metadata['booking_start_time'],
            $timezone,
        );
        $endsAt = Carbon::parse(
            $metadata['booking_date'].' '.$metadata['booking_end_time'],
            $timezone,
        );

        $booking = $payment->team->bookings()->create([
            'product_id' => null,
            'customer_id' => $customer->id,
            'photoshoot_id' => $photoshoot->id,
            'product_name' => $payment->description,
            'duration_minutes' => max(1, (int) $startsAt->diffInMinutes($endsAt)),
            'status' => BookingStatus::Confirmed,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'timezone' => $timezone,
            'total_amount' => $payment->amount,
            'deposit_amount' => 0,
            'balance_amount' => 0,
            'currency' => $payment->currency,
            'customer_name' => $payment->customer_name ?: ($customer->name ?? $payment->customer_email ?? __('Customer')),
            'customer_email' => $payment->customer_email,
            'customer_phone' => $payment->customer_phone,
            'source' => 'payment_link',
            'payment_method' => $payment->payment_method,
            'payment_kind' => PaymentKind::Full,
            'confirmed_at' => now(),
            'notes' => __('Created from a payment link.'),
        ]);

        $payment->update(['booking_id' => $booking->id]);

        return $booking;
    }

    private function createInvoice(Payment $payment): void
    {
        try {
            if ($payment->invoice()->exists()) {
                return;
            }

            $this->invoices->createFromPayment($payment);
        } catch (\Throwable $exception) {
            Log::warning('Payment-link invoice draft could not be created', [
                'payment_id' => $payment->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function notifyBookingCreated(Payment $payment, ?Photoshoot $photoshoot, array $metadata): void
    {
        if ($photoshoot === null) {
            return;
        }

        $timezone = (string) ($metadata['timezone'] ?? 'UTC');
        $date = Carbon::parse((string) $metadata['booking_date'], $timezone);
        $startTime = Carbon::parse((string) $metadata['booking_start_time'], $timezone);
        $endTime = Carbon::parse((string) $metadata['booking_end_time'], $timezone);

        $owner = $payment->team->owner;

        if ($owner !== null) {
            $owner->notify(new BookingCreated($photoshoot, $date, $startTime, $endTime, $payment, $timezone));
        }

        if (filled($payment->customer_email)) {
            Notification::route('mail', $payment->customer_email)
                ->notify(new BookingCreated($photoshoot, $date, $startTime, $endTime, $payment, $timezone));
        }
    }

    private function notifyBizumConfirmed(Payment $payment): void
    {
        if (blank($payment->customer_email)) {
            return;
        }

        Notification::route('mail', $payment->customer_email)
            ->notify(new PaymentLinkBizumConfirmed($payment));
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function usesBookingMetadata(array $metadata): bool
    {
        return (bool) ($metadata['booking'] ?? false)
            && filled($metadata['booking_date'] ?? null)
            && filled($metadata['booking_start_time'] ?? null)
            && filled($metadata['booking_end_time'] ?? null);
    }
}
