<?php

namespace App\Listeners;

use App\Console\ScheduledTasks;
use App\Models\ScheduledTask;
use DateTimeZone;
use Illuminate\Console\Events\ScheduledBackgroundTaskFinished;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskSkipped;
use Illuminate\Console\Events\ScheduledTaskStarting;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Persists the last execution state of every scheduled task.
 *
 * Laravel dispatches these events from schedule:run and schedule:work. Writes
 * are best-effort: a monitoring failure must never stop the scheduler itself.
 */
class ScheduledTaskEventSubscriber
{
    public function subscribe(Dispatcher $events): void
    {
        $events->listen(ScheduledTaskStarting::class, [$this, 'starting']);
        $events->listen(ScheduledTaskFinished::class, [$this, 'finished']);
        $events->listen(ScheduledTaskFailed::class, [$this, 'failed']);
        $events->listen(ScheduledTaskSkipped::class, [$this, 'skipped']);
        $events->listen(ScheduledBackgroundTaskFinished::class, [$this, 'backgroundFinished']);
    }

    public function starting(ScheduledTaskStarting $event): void
    {
        $this->safely(function () use ($event): void {
            Cache::put(
                ScheduledTasks::HEARTBEAT_CACHE_KEY,
                now()->timestamp,
                now()->addMinutes(10),
            );

            $this->task($event->task)->fill([
                'command' => $this->command($event->task),
                'expression' => $event->task->expression,
                'timezone' => $this->timezone($event->task),
                'last_status' => ScheduledTask::STATUS_RUNNING,
                'last_started_at' => now(),
                'last_exception' => null,
            ])->save();
        });
    }

    public function finished(ScheduledTaskFinished $event): void
    {
        $this->safely(function () use ($event): void {
            if ($event->task->runInBackground) {
                return;
            }

            $this->task($event->task)->fill([
                'last_status' => ScheduledTask::STATUS_SUCCESS,
                'last_finished_at' => now(),
                'last_runtime' => $event->runtime,
                'last_exception' => null,
            ])->save();
        });
    }

    public function backgroundFinished(ScheduledBackgroundTaskFinished $event): void
    {
        $this->safely(function () use ($event): void {
            $this->task($event->task)->fill([
                'last_status' => ScheduledTask::STATUS_SUCCESS,
                'last_finished_at' => now(),
                'last_exception' => null,
            ])->save();
        });
    }

    public function failed(ScheduledTaskFailed $event): void
    {
        $this->safely(function () use ($event): void {
            $task = $this->task($event->task);
            $startedAt = $task->last_started_at;

            $task->fill([
                'last_status' => ScheduledTask::STATUS_FAILED,
                'last_finished_at' => now(),
                'last_runtime' => $startedAt
                    ? max(0, $startedAt->diffInMilliseconds(now()) / 1000)
                    : null,
                'last_exception' => $this->exception($event->exception),
            ])->save();
        });
    }

    public function skipped(ScheduledTaskSkipped $event): void
    {
        $this->safely(function () use ($event): void {
            $task = $this->task($event->task);

            $attributes = [
                'last_skipped_at' => now(),
            ];

            if ($task->last_status !== ScheduledTask::STATUS_RUNNING) {
                $attributes['last_status'] = ScheduledTask::STATUS_SKIPPED;
            }

            $task->fill($attributes)->save();
        });
    }

    private function task(Event $event): ScheduledTask
    {
        return ScheduledTask::query()->firstOrNew(['name' => $this->name($event)]);
    }

    private function name(Event $event): string
    {
        return $event->description ?: $event->getSummaryForDisplay();
    }

    private function command(Event $event): ?string
    {
        return $event->command ?: $event->description;
    }

    private function timezone(Event $event): ?string
    {
        return $event->timezone instanceof DateTimeZone
            ? $event->timezone->getName()
            : $event->timezone;
    }

    private function exception(Throwable $exception): string
    {
        return Str::limit(
            $exception::class.': '.$exception->getMessage()."\n\n".$exception->getTraceAsString(),
            4000,
        );
    }

    private function safely(callable $callback): void
    {
        try {
            $callback();
        } catch (Throwable $exception) {
            Log::warning('Unable to record scheduled task status.', [
                'exception' => $exception->getMessage(),
            ]);
        }
    }
}
