<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A Stripe webhook that has already been handled.
 *
 * Stripe retries deliveries and may send the same event twice. Keeping the
 * id makes fulfilment idempotent: a booking is confirmed once, no matter how
 * many times the event arrives.
 */
class StripeEvent extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'processed_at' => 'datetime',
        ];
    }
}
