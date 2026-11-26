<?php

namespace App\Models;

use App\Enums\PaymentKind;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Cashier\Cashier;

class Payment extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'amount' => 'integer',
        'completed_at' => 'datetime',
        'payment_kind' => PaymentKind::class,
    ];

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function photoshoot()
    {
        return $this->belongsTo(Photoshoot::class);
    }

    /**
     * The booking this payment settles, if it came from the booking flow.
     */
    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    /**
     * The invoice that settles this payment.
     */
    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
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
