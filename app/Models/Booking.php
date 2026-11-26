<?php

namespace App\Models;

use App\Enums\BookingStatus;
use App\Enums\PaymentKind;
use App\Enums\PaymentMethod;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Database\Factories\BookingFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A reserved slot.
 *
 * `starts_at` and `ends_at` are stored in UTC. The `timezone` column keeps
 * the photographer's zone so emails, the calendar and the public page can
 * render the session where it happens. Amounts and the product name are
 * frozen when the booking is created; editing the product later must not
 * rewrite past bookings.
 *
 * @property int $id
 * @property string $ulid
 * @property int $team_id
 * @property int|null $product_id
 * @property int|null $customer_id
 * @property int|null $photoshoot_id
 * @property string $product_name
 * @property int $duration_minutes
 * @property BookingStatus $status
 * @property CarbonImmutable $starts_at
 * @property CarbonImmutable $ends_at
 * @property string $timezone
 * @property int $total_amount
 * @property int $deposit_amount
 * @property int $balance_amount
 * @property string $currency
 * @property string $customer_name
 * @property string $customer_email
 * @property string|null $customer_phone
 * @property string|null $notes
 * @property string $source
 * @property PaymentMethod|null $payment_method
 * @property PaymentKind|null $payment_kind
 * @property CarbonImmutable|null $hold_expires_at
 * @property CarbonImmutable|null $confirmed_at
 * @property CarbonImmutable|null $cancelled_at
 * @property CarbonImmutable|null $completed_at
 * @property string|null $cancellation_reason
 * @property string|null $policy_snapshot
 * @property CarbonImmutable|null $policy_accepted_at
 * @property CarbonImmutable|null $balance_due_at
 */
class Booking extends Model
{
    /** @use HasFactory<BookingFactory> */
    use HasFactory;

    protected $guarded = [];

    protected static function booted(): void
    {
        static::creating(function (Booking $booking) {
            $booking->ulid ??= (string) Str::ulid();
        });
    }

    protected function casts(): array
    {
        return [
            'status' => BookingStatus::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'duration_minutes' => 'integer',
            'total_amount' => 'integer',
            'deposit_amount' => 'integer',
            'balance_amount' => 'integer',
            'payment_method' => PaymentMethod::class,
            'payment_kind' => PaymentKind::class,
            'hold_expires_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'completed_at' => 'datetime',
            'policy_accepted_at' => 'datetime',
            'balance_due_at' => 'datetime',
        ];
    }

    /**
     * Ranges are stored in UTC. Laravel writes a datetime cast in the
     * timezone of the given instance, so a local Carbon coming from the UI
     * would otherwise be stored as if it were UTC and shift the session by
     * the offset.
     */
    protected function startsAt(): Attribute
    {
        return Attribute::make(set: fn (mixed $value) => $this->toUtc($value));
    }

    protected function endsAt(): Attribute
    {
        return Attribute::make(set: fn (mixed $value) => $this->toUtc($value));
    }

    private function toUtc(mixed $value): ?CarbonImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        return ($value instanceof CarbonInterface
            ? CarbonImmutable::instance($value)
            : CarbonImmutable::parse($value))->utc();
    }

    /**
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * The job created when the booking is paid.
     *
     * @return BelongsTo<Photoshoot, $this>
     */
    public function photoshoot(): BelongsTo
    {
        return $this->belongsTo(Photoshoot::class);
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * @return HasMany<BookingReminder, $this>
     */
    public function reminders(): HasMany
    {
        return $this->hasMany(BookingReminder::class);
    }

    /**
     * Bookings that still reserve their slot.
     *
     * A pending hold stops blocking as soon as it expires, even if a cleanup
     * command has not run yet, so the calendar never keeps a slot hostage.
     *
     * @param  Builder<Booking>  $query
     */
    public function scopeBlocking(Builder $query): void
    {
        $query->whereIn('status', [
            BookingStatus::PendingPayment->value,
            BookingStatus::PendingBizum->value,
            BookingStatus::Confirmed->value,
        ])->where(function (Builder $query) {
            $query->whereNull('hold_expires_at')
                ->orWhere('hold_expires_at', '>', now());
        });
    }

    /**
     * @param  Builder<Booking>  $query
     */
    public function scopeOverlapping(Builder $query, CarbonInterface $startsAt, CarbonInterface $endsAt): void
    {
        $query->where('starts_at', '<', $endsAt)
            ->where('ends_at', '>', $startsAt);
    }

    /**
     * Whether a pending booking lost its slot because the payment window
     * closed.
     */
    public function isHoldExpired(): bool
    {
        return $this->hold_expires_at !== null
            && $this->hold_expires_at->isPast()
            && $this->status->isPending();
    }
}
