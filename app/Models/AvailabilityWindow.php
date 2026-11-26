<?php

namespace App\Models;

use Database\Factories\AvailabilityWindowFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A recurring opening window, one row per weekday.
 *
 * `weekday` is ISO-8601 (1 = Monday, 7 = Sunday) to match Carbon. Windows of
 * the same weekday must not overlap; the settings form replaces the whole
 * week at once so that rule is enforced in one place.
 */
class AvailabilityWindow extends Model
{
    /** @use HasFactory<AvailabilityWindowFactory> */
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'weekday' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
