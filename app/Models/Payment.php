<?php

namespace App\Models;

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
    ];

    public function team()
    {
        return $this->belongsTo(Team::class);
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
