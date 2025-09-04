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

    protected static function booted(): void
    {
        static::creating(function (BioLink $bioLink) {
            if (is_null($bioLink->order)) {
                $bioLink->order = static::where('team_id', $bioLink->team_id)->max('order') + 1;
            }
        });
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function reorder(int $newOrder): void
    {
        $currentOrder = $this->order;

        if ($newOrder > $currentOrder) {
            $this->team->bioLinks()
                ->where('order', '>', $currentOrder)
                ->where('order', '<=', $newOrder)
                ->decrement('order');
        } elseif ($newOrder < $currentOrder) {
            $this->team->bioLinks()
                ->where('order', '>=', $newOrder)
                ->where('order', '<', $currentOrder)
                ->increment('order');
        }

        $this->update(['order' => $newOrder]);
    }
}
