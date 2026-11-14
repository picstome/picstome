<?php

namespace App\Models;

use Laravel\Cashier\Subscription as CashierSubscription;

/**
 * Cashier's subscription model with the local scheduling columns cast.
 */
class Subscription extends CashierSubscription
{
    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'scheduled_change_at' => 'datetime',
            'current_period_end' => 'datetime',
        ]);
    }
}
