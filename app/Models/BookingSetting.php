<?php

namespace App\Models;

use App\Enums\DepositType;
use Database\Factories\BookingSettingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Booking configuration of a team: schedule rules, deposit fallback, the
 * cancellation policy the client accepts and the manual Bizum details.
 *
 * A row is created lazily with the defaults from `config/bookings.php` the
 * first time the team opens bookings, so every reader can assume it exists.
 *
 * @property int $id
 * @property int $team_id
 * @property string $timezone
 * @property int $slot_step_minutes
 * @property int $buffer_minutes
 * @property int $min_notice_hours
 * @property int $horizon_days
 * @property int|null $max_sessions_per_day
 * @property DepositType $default_deposit_type
 * @property int $default_deposit_value
 * @property string|null $cancellation_policy
 * @property bool $auto_issue_invoices
 * @property bool $reminder_first_enabled
 * @property int $reminder_first_hours
 * @property bool $reminder_second_enabled
 * @property int $reminder_second_hours
 * @property bool $card_enabled
 */
class BookingSetting extends Model
{
    /** @use HasFactory<BookingSettingFactory> */
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'slot_step_minutes' => 'integer',
            'buffer_minutes' => 'integer',
            'min_notice_hours' => 'integer',
            'horizon_days' => 'integer',
            'max_sessions_per_day' => 'integer',
            'default_deposit_type' => DepositType::class,
            'default_deposit_value' => 'integer',
            'auto_issue_invoices' => 'boolean',
            'reminder_first_enabled' => 'boolean',
            'reminder_first_hours' => 'integer',
            'reminder_second_enabled' => 'boolean',
            'reminder_second_hours' => 'integer',
            'card_enabled' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Settings of a team, created with the application defaults if missing.
     */
    public static function forTeam(Team $team): self
    {
        return self::firstOrCreate(
            ['team_id' => $team->id],
            config('bookings.defaults'),
        );
    }
}
