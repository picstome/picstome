<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Cache;

/**
 * Single source of truth for the application's scheduled tasks.
 *
 * The admin queue manager builds a throwaway Schedule with the same
 * definitions, so it can list tasks that have not run yet.
 */
class ScheduledTasks
{
    public const HEARTBEAT_NAME = 'scheduler-heartbeat';

    public const HEARTBEAT_CACHE_KEY = 'queue-manager:heartbeat:scheduler';

    public static function register(Schedule $schedule): void
    {
        // Stagger the daily maintenance jobs so they do not all start at
        // midnight on the same worker.
        $schedule->command('galleries:delete-expired')
            ->dailyAt('00:00')
            ->name('galleries:delete-expired');

        $schedule->command('galleries:send-expiration-reminders')
            ->dailyAt('09:00')
            ->name('galleries:send-expiration-reminders');

        $schedule->command('subscriptions:cleanup')
            ->dailyAt('00:10')
            ->name('subscriptions:cleanup');

        $schedule->command('cleanup:livewire-tmp --force')
            ->dailyAt('00:20')
            ->withoutOverlapping()
            ->name('cleanup:livewire-tmp');

        $schedule->command('ingest:ftp-uploads')
            ->everyMinute()
            ->name('ingest:ftp-uploads');

        $schedule->command('ftp:cleanup-inbox --force')
            ->dailyAt('00:35')
            ->name('ftp:cleanup-inbox');

        $schedule->command('gallery-archives:prune --force')
            ->dailyAt('00:30')
            ->name('gallery-archives:prune');

        $schedule->command('temp:cleanup --force')
            ->dailyAt('00:40')
            ->name('temp:cleanup');

        // Keep expired app tokens around briefly so the connected apps screen
        // can still explain why an application stopped working.
        $schedule->command('sanctum:prune-expired --hours=48')
            ->dailyAt('00:50')
            ->name('sanctum:prune-expired');

        $schedule->call(function (): void {
            Cache::put(
                self::HEARTBEAT_CACHE_KEY,
                now()->timestamp,
                now()->addMinutes(10),
            );
        })->everyMinute()->name(self::HEARTBEAT_NAME);
    }
}
