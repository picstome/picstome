<?php

namespace App\Services;

use App\Console\ScheduledTasks;
use App\Models\ScheduledTask;
use Carbon\CarbonInterface;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;

/**
 * Builds the read-only scheduled tasks panel from the schedule definition and
 * the last execution state recorded by ScheduledTaskEventSubscriber.
 */
class ScheduledTaskMonitorService
{
    /**
     * @return list<array{
     *     name: string,
     *     command: string,
     *     expression: string,
     *     frequency: string,
     *     next_run_at: ?CarbonInterface,
     *     last_status: string,
     *     last_started_at: ?CarbonInterface,
     *     last_finished_at: ?CarbonInterface,
     *     last_skipped_at: ?CarbonInterface,
     *     last_runtime: ?float,
     *     last_exception: ?string,
     *     is_stale_running: bool
     * }>
     */
    public function tasks(): array
    {
        $schedule = new Schedule(config('app.schedule_timezone', config('app.timezone')));
        ScheduledTasks::register($schedule);

        $statuses = ScheduledTask::query()->get()->keyBy('name');

        return collect($schedule->events())
            ->reject(fn (Event $event): bool => $this->name($event) === ScheduledTasks::HEARTBEAT_NAME)
            ->map(function (Event $event) use ($statuses): array {
                $name = $this->name($event);
                $status = $statuses->get($name);

                return [
                    'name' => $name,
                    'command' => $this->command($event, $name),
                    'expression' => $event->expression,
                    'frequency' => $this->frequency($event),
                    'next_run_at' => $this->nextRun($event),
                    'last_status' => $status?->last_status ?? ScheduledTask::STATUS_NEVER,
                    'last_started_at' => $status?->last_started_at,
                    'last_finished_at' => $status?->last_finished_at,
                    'last_skipped_at' => $status?->last_skipped_at,
                    'last_runtime' => $status?->last_runtime,
                    'last_exception' => $status?->last_exception,
                    'is_stale_running' => $this->isStaleRunning($status),
                ];
            })
            ->sortBy('next_run_at')
            ->values()
            ->all();
    }

    /**
     * @return array{status: string, last_seen_at: ?CarbonInterface, age_seconds: ?int}
     */
    public function schedulerStatus(): array
    {
        $timestamp = Cache::get(ScheduledTasks::HEARTBEAT_CACHE_KEY);
        $lastSeen = is_numeric($timestamp) ? now()->setTimestamp((int) $timestamp) : null;
        $age = $lastSeen ? max(0, now()->timestamp - $lastSeen->timestamp) : null;
        $staleAfter = (int) config('picstome.queue_manager.heartbeat_stale_after_seconds', 180);

        $status = match (true) {
            $lastSeen === null => 'unknown',
            $age <= $staleAfter => 'running',
            default => 'stopped',
        };

        return [
            'status' => $status,
            'last_seen_at' => $lastSeen,
            'age_seconds' => $age,
        ];
    }

    private function name(Event $event): string
    {
        return $event->description ?: $event->getSummaryForDisplay();
    }

    private function command(Event $event, string $name): string
    {
        if (! $event->command) {
            return $name;
        }

        $command = Str::after($event->command, "'artisan' ");

        return $command === $event->command
            ? $event->command
            : 'php artisan '.$command;
    }

    private function nextRun(Event $event): ?CarbonInterface
    {
        try {
            return $event->nextRunDate(now())->timezone(config('app.timezone'));
        } catch (Throwable) {
            return null;
        }
    }

    private function frequency(Event $event): string
    {
        return match ($event->expression) {
            '* * * * *' => __('Every minute'),
            '*/5 * * * *' => __('Every 5 minutes'),
            '*/10 * * * *' => __('Every 10 minutes'),
            '*/15 * * * *' => __('Every 15 minutes'),
            '*/30 * * * *' => __('Every 30 minutes'),
            '0 * * * *' => __('Hourly'),
            '0 0 * * *' => __('Daily at midnight'),
            '0 9 * * *' => __('Daily at 09:00'),
            '0 0 * * 0' => __('Weekly'),
            default => $this->dailyAtLabel($event->expression) ?? $event->expression,
        };
    }

    private function dailyAtLabel(string $expression): ?string
    {
        if (! preg_match('/^(\d{1,2}) (\d{1,2}) \* \* \*$/', $expression, $matches)) {
            return null;
        }

        return __('Daily at :time', [
            'time' => sprintf('%02d:%02d', (int) $matches[2], (int) $matches[1]),
        ]);
    }

    private function isStaleRunning(?ScheduledTask $task): bool
    {
        if (! $task || $task->last_status !== ScheduledTask::STATUS_RUNNING || ! $task->last_started_at) {
            return false;
        }

        $staleAfter = (int) config('picstome.queue_manager.scheduled_task_stale_after_seconds', 3600);

        return $task->last_started_at->lt(now()->subSeconds($staleAfter));
    }
}
