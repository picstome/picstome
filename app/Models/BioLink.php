<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BioLink extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'order' => 'integer',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    protected static function booted(): void
    {
        static::creating(function (BioLink $bioLink) {
            if (is_null($bioLink->order)) {
                $bioLink->order = static::where('team_id', $bioLink->team_id)->max('order') + 1;
            }
        });
    }
}
