<?php

namespace App\Models;

use App\Enums\PaymentKind;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Cashier\Cashier;

/**
 * @property-read Team $team
 * @property-read Photoshoot|null $photoshoot
 * @property-read Booking|null $booking
 * @property-read Invoice|null $invoice
 */
class Payment extends Model
{
    use HasFactory;

    protected $guarded = [];

    /**
     * Existing payments predate the status column and are completed.
     */
    protected $attributes = [
        'status' => 'completed',
    ];

    protected $casts = [
        'amount' => 'integer',
        'completed_at' => 'datetime',
        'expires_at' => 'datetime',
        'metadata' => 'array',
        'payment_kind' => PaymentKind::class,
        'payment_method' => PaymentMethod::class,
        'status' => PaymentStatus::class,
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function photoshoot(): BelongsTo
    {
        return $this->belongsTo(Photoshoot::class);
    }

    /**
     * The booking this payment settles, if it came from the booking flow.
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /**
     * The invoice that settles this payment.
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * @param  Builder<Payment>  $query
     */
    public function scopeCompleted(Builder $query): void
    {
        $query->where('status', PaymentStatus::Completed->value);
    }

    /**
     * @param  Builder<Payment>  $query
     */
    public function scopePendingBizum(Builder $query): void
    {
        $query->where('status', PaymentStatus::PendingBizum->value);
    }

    public function isCompleted(): bool
    {
        return $this->status === PaymentStatus::Completed;
    }

    public function isPendingBizum(): bool
    {
        return $this->status === PaymentStatus::PendingBizum;
    }

    public function isExpired(): bool
    {
        return $this->status === PaymentStatus::Expired;
    }

    /**
     * Get the formatted amount using Laravel Cashier.
     */
    protected function formattedAmount(): Attribute
    {
        return Attribute::make(
            get: fn () => Cashier::formatAmount($this->amount, $this->currency)
        );
    }
}
