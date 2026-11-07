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

    /**
     * Move the link to a zero-based position in the team's list.
     *
     * Livewire's `wire:sort` hands the drop index (0, 1, 2, ...) while the
     * stored `order` column is not guaranteed to be contiguous — legacy rows
     * start at 1 and earlier moves could leave gaps. Resequencing the whole
     * list keeps `order` aligned with the rendered position, so adjacent
     * drops are persisted instead of being treated as a no-op.
     */
    public function reorder(int $newIndex): void
    {
        $links = $this->team->bioLinks()->orderBy('order')->orderBy('id')->get();

        $ordered = $links->reject(fn (BioLink $link) => $link->is($this))->values();

        $newIndex = max(0, min($newIndex, $ordered->count()));

        $ordered->splice($newIndex, 0, [$this]);

        foreach ($ordered as $position => $link) {
            if ($link->order !== $position) {
                $link->update(['order' => $position]);
            }
        }
    }
}
