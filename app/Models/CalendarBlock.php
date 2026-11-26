<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Database\Factories\CalendarBlockFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A period the photographer is unavailable: a holiday, a personal
 * appointment or a partial day.
 *
 * A full-day block and a two-hour block are the same row. Ranges may span
 * several days, so a two-week holiday is one record.
 *
 * @property int $id
 * @property int $team_id
 * @property CarbonImmutable $starts_at
 * @property CarbonImmutable $ends_at
 * @property bool $all_day
 * @property string|null $reason
 */
class CalendarBlock extends Model
{
    /** @use HasFactory<CalendarBlockFactory> */
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'all_day' => 'boolean',
        ];
    }

    /**
     * Blocks are stored in UTC for the same reason as bookings: the value
     * may arrive as a local Carbon from the settings form.
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
     * Whether this block covers the whole of a local day.
     *
     * Full-day blocks end at 23:59:59, so callers pass the same second as the
     * end of the day to avoid comparing against 23:59:59.999999.
     */
    public function coversDay(CarbonInterface $dayStart, CarbonInterface $dayEnd): bool
    {
        return $this->starts_at->lessThanOrEqualTo($dayStart)
            && $this->ends_at->greaterThanOrEqualTo($dayEnd);
    }

    /**
     * @param  Builder<CalendarBlock>  $query
     */
    public function scopeOverlapping(Builder $query, CarbonInterface $startsAt, CarbonInterface $endsAt): void
    {
        $query->where('starts_at', '<', $endsAt)
            ->where('ends_at', '>', $startsAt);
    }
}
