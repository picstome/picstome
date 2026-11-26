<?php

namespace App\Services\Booking;

use App\Enums\BookingStatus;
use App\Enums\PaymentKind;
use App\Enums\PaymentMethod;
use App\Exceptions\BookingLimitReachedException;
use App\Exceptions\SlotUnavailableException;
use App\Models\Booking;
use App\Models\BookingSetting;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Team;
use App\Notifications\BookingCancelled;
use App\Notifications\BookingCancelledForPhotographer;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use InvalidArgumentException;
use LogicException;

/**
 * Creates, confirms, cancels and expires bookings.
 *
 * Creating a hold serialises on the team row inside a transaction. A solo
 * photographer receives few bookings at a time, so a team-wide lock is
 * simpler and safer than a per-day lock table, and it makes the "check the
 * slot then write the booking" pair atomic. SQLite ignores the lock, which
 * is fine because its tests run sequentially.
 */
class BookingService
{
    public function __construct(private readonly AvailabilityService $availability) {}

    /**
     * Reserve a slot while the client pays.
     *
     * @param  array{name: string, email: string, phone?: ?string, notes?: ?string, customer_id?: ?int}  $customer
     */
    public function hold(
        Team $team,
        Product $product,
        CarbonImmutable $startsAt,
        array $customer,
        PaymentMethod $method = PaymentMethod::Card,
        ?string $policy = null,
        string $source = 'public',
    ): Booking {
        if ($product->team_id !== $team->id) {
            throw new InvalidArgumentException('The product does not belong to the team.');
        }

        return DB::transaction(function () use ($team, $product, $startsAt, $customer, $method, $policy, $source) {
            Team::query()->whereKey($team->id)->lockForUpdate()->firstOrFail();

            if ($source === 'public' && ! app(BookingPlanLimitService::class)->canReceive($team)) {
                throw new BookingLimitReachedException;
            }

            if (! $this->availability->isAvailable($team, $product, $startsAt)) {
                throw new SlotUnavailableException;
            }

            $settings = BookingSetting::forTeam($team);
            $total = $product->price;
            $deposit = $product->depositAmount($settings);
            $status = $method === PaymentMethod::Bizum
                ? BookingStatus::PendingBizum
                : BookingStatus::PendingPayment;

            $expiresAt = $status === BookingStatus::PendingBizum
                ? now()->addHours(config('bookings.bizum_expiry_hours'))
                : now()->addMinutes(config('bookings.hold_minutes'));

            $customerModel = $this->customerFor($team, $customer);

            return $team->bookings()->create([
                'product_id' => $product->id,
                'customer_id' => $customerModel->id,
                'product_name' => $product->name,
                'duration_minutes' => $product->duration_minutes,
                'status' => $status,
                'starts_at' => $startsAt->utc(),
                'ends_at' => $startsAt->utc()->addMinutes($product->duration_minutes),
                'timezone' => $settings->timezone,
                'total_amount' => $total,
                'deposit_amount' => $deposit,
                'balance_amount' => max(0, $total - $deposit),
                'currency' => $product->currency,
                'customer_name' => $customer['name'],
                'customer_email' => $customer['email'],
                'customer_phone' => $customer['phone'] ?? null,
                'notes' => $customer['notes'] ?? null,
                'payment_method' => $method,
                'payment_kind' => PaymentKind::Full,
                'source' => $source,
                'hold_expires_at' => $expiresAt,
                'policy_snapshot' => $policy,
                'policy_accepted_at' => $policy !== null ? now() : null,
            ]);
        });
    }

    /**
     * Create a booking typed in by the photographer, for clients who book by
     * phone or in person.
     *
     * It is confirmed straight away because the photographer is the one
     * entering it. Cash is only the placeholder method; marking it paid is a
     * separate step once the payment is actually received.
     *
     * @param  array{name: string, email: string, phone?: ?string, notes?: ?string, customer_id?: ?int}  $customer
     */
    public function createManual(
        Team $team,
        Product $product,
        CarbonImmutable $startsAt,
        array $customer,
        ?string $notes = null,
    ): Booking {
        $booking = $this->hold(
            $team,
            $product,
            $startsAt,
            array_merge($customer, ['notes' => $notes]),
            PaymentMethod::Cash,
            null,
            'manual',
        );

        return $this->confirm($booking, PaymentMethod::Cash);
    }

    /**
     * Resolve the client of the booking.
     *
     * An explicit id wins (the photographer picked an existing customer).
     * Otherwise the email is the identity: the customer is reused if the team
     * already has one and created when it does not, so manual bookings and the
     * public page feed the same address book.
     *
     * @param  array{name: string, email: string, phone?: ?string, notes?: ?string, customer_id?: ?int}  $customer
     */
    private function customerFor(Team $team, array $customer): Customer
    {
        $customerId = $customer['customer_id'] ?? null;

        if ($customerId !== null) {
            return $team->customers()->findOrFail($customerId);
        }

        $existing = $team->customers()->where('email', $customer['email'])->first();

        if ($existing === null) {
            return $team->customers()->create([
                'email' => $customer['email'],
                'name' => $customer['name'],
                'phone' => $customer['phone'] ?? null,
            ]);
        }

        // The email is the identity, but the name typed in the latest booking
        // is newer than the address book: keep the customer card and the
        // invoices in sync with it.
        $updates = [];

        if (filled($customer['name']) && $existing->name !== $customer['name']) {
            $updates['name'] = $customer['name'];
        }

        if (filled($customer['phone'] ?? null) && $existing->phone !== $customer['phone']) {
            $updates['phone'] = $customer['phone'];
        }

        if ($updates !== []) {
            $existing->update($updates);
        }

        return $existing;
    }

    /**
     * Turn a pending booking into a confirmed session.
     *
     * Payments, invoices and the photoshoot are created by the payment
     * fulfilment, not here, so this method stays a pure state transition.
     */
    public function confirm(Booking $booking, PaymentMethod $method): Booking
    {
        if ($booking->status->isFinal()) {
            throw new LogicException('A finished booking cannot be confirmed.');
        }

        $booking->update([
            'status' => BookingStatus::Confirmed,
            'payment_method' => $method,
            'confirmed_at' => now(),
            'hold_expires_at' => null,
        ]);

        return $booking;
    }

    public function cancel(Booking $booking, ?string $reason = null): Booking
    {
        if ($booking->status === BookingStatus::Cancelled) {
            return $booking;
        }

        $booking->update([
            'status' => BookingStatus::Cancelled,
            'cancelled_at' => now(),
            'cancellation_reason' => $reason,
            'hold_expires_at' => null,
        ]);

        $this->notifyCancelled($booking);

        $this->removePhotoshoot($booking);

        return $booking;
    }

    /**
     * A cancelled session must not keep a ghost job in the photoshoots list.
     * Galleries and contracts are never destroyed: if the session already has
     * client material, the photoshoot is kept and the situation is logged.
     */
    private function removePhotoshoot(Booking $booking): void
    {
        $photoshoot = $booking->photoshoot;

        if ($photoshoot === null) {
            return;
        }

        if ($photoshoot->galleries()->exists() || $photoshoot->contracts()->exists()) {
            Log::warning('Cancelled booking keeps its photoshoot because it has galleries or contracts', [
                'booking' => $booking->ulid,
                'photoshoot' => $photoshoot->id,
            ]);

            return;
        }

        $photoshoot->delete();
    }

    /**
     * The client and the photographer both need to know; the calendar is not
     * an inbox.
     */
    private function notifyCancelled(Booking $booking): void
    {
        Notification::route('mail', $booking->customer_email)
            ->notify(new BookingCancelled($booking));

        $owner = $booking->team->owner;

        if ($owner === null) {
            Log::warning('Cancelled booking has no owner to notify', ['booking' => $booking->ulid]);

            return;
        }

        $owner->notify(new BookingCancelledForPhotographer($booking));
    }

    /**
     * Release the slots of pending bookings whose payment window closed.
     *
     * The availability query already ignores expired holds, so this only
     * tidies the state and lets the agenda show the booking as expired.
     */
    public function expirePending(): int
    {
        return Booking::query()
            ->whereIn('status', [
                BookingStatus::PendingPayment->value,
                BookingStatus::PendingBizum->value,
            ])
            ->whereNotNull('hold_expires_at')
            ->where('hold_expires_at', '<=', now())
            ->update([
                'status' => BookingStatus::Expired->value,
                'updated_at' => now(),
            ]);
    }
}
