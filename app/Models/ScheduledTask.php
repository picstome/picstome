<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Last known execution state of a scheduled task.
 *
 * The scheduler subscriber keeps one row per task. This is intentionally not a
 * run history: the admin panel only shows the latest execution.
 */
class ScheduledTask extends Model
{
    public const STATUS_NEVER = 'never';

    public const STATUS_RUNNING = 'running';

    public const STATUS_SUCCESS = 'success';

    public const STATUS_FAILED = 'failed';

    public const STATUS_SKIPPED = 'skipped';

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_started_at' => 'datetime',
            'last_finished_at' => 'datetime',
            'last_skipped_at' => 'datetime',
            'last_runtime' => 'float',
        ];
    }
}
