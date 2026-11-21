<?php

namespace App\Traits\Queue;

use App\Services\QueueMonitorService;
use App\Services\ScheduledTaskMonitorService;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;

/**
 * Read-only queue, worker and scheduler telemetry computed properties.
 */
trait MonitorsQueueHealth
{
    #[Computed]
    public function stats(): array
    {
        return app(QueueMonitorService::class)->stats();
    }

    #[Computed]
    public function queues(): array
    {
        return app(QueueMonitorService::class)->queues();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    #[Computed]
    public function queuedJobs(): Collection
    {
        return app(QueueMonitorService::class)->queuedJobs($this->queue, $this->queuedLimit);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    #[Computed]
    public function failedJobs(): Collection
    {
        return app(QueueMonitorService::class)->failedJobs($this->queue, $this->failedLimit);
    }

    #[Computed]
    public function selectedJob(): ?array
    {
        return $this->selectedJobId
            ? app(QueueMonitorService::class)->jobDetails($this->selectedJobId)
            : null;
    }

    #[Computed]
    public function selectedFailedJob(): ?array
    {
        return $this->selectedFailedUuid
            ? app(QueueMonitorService::class)->failedJobDetails($this->selectedFailedUuid)
            : null;
    }

    /**
     * @return array<string, mixed>
     */
    #[Computed]
    public function workerStatus(): array
    {
        return app(QueueMonitorService::class)->workerStatus();
    }

    #[Computed]
    public function workerLabel(): string
    {
        return match ($this->workerStatus['status']) {
            'running' => __('Running'),
            'busy' => __('Busy or stopped'),
            'stopped' => __('Stopped'),
            default => __('Unknown'),
        };
    }

    #[Computed]
    public function workerHint(): string
    {
        $status = $this->workerStatus;

        if (! $status['last_seen_at']) {
            return __('No heartbeat recorded yet.');
        }

        $hint = __('Last heartbeat :time', ['time' => $status['last_seen_at']->diffForHumans()]);

        if ($status['queues']) {
            $hint .= ' · '.$status['queues'];
        }

        return $hint;
    }

    #[Computed]
    public function workerCardStatus(): string
    {
        return match ($this->workerStatus['status']) {
            'running' => 'green',
            'busy' => 'amber',
            'stopped' => 'red',
            default => 'zinc',
        };
    }

    #[Computed]
    public function schedulerStatus(): array
    {
        return app(ScheduledTaskMonitorService::class)->schedulerStatus();
    }

    #[Computed]
    public function schedulerLabel(): string
    {
        return match ($this->schedulerStatus['status']) {
            'running' => __('Running'),
            'stopped' => __('Stopped'),
            default => __('Unknown'),
        };
    }

    #[Computed]
    public function schedulerHint(): string
    {
        $status = $this->schedulerStatus;

        if (! $status['last_seen_at']) {
            return __('No heartbeat recorded yet.');
        }

        return __('Last tick :time', ['time' => $status['last_seen_at']->diffForHumans()]);
    }

    #[Computed]
    public function schedulerCardStatus(): string
    {
        return match ($this->schedulerStatus['status']) {
            'running' => 'green',
            'stopped' => 'red',
            default => 'zinc',
        };
    }

    #[Computed]
    public function scheduledTasks(): array
    {
        return app(ScheduledTaskMonitorService::class)->tasks();
    }

    #[Computed]
    public function selectedScheduledTask(): ?array
    {
        if (! $this->selectedTaskName) {
            return null;
        }

        return collect($this->scheduledTasks)
            ->firstWhere('name', $this->selectedTaskName);
    }

    /**
     * @return array{total: int, failed: int, running: int, stale: int, healthy: int, never: int}
     */
    #[Computed]
    public function taskSummary(): array
    {
        $tasks = collect($this->scheduledTasks);

        return [
            'total' => $tasks->count(),
            'failed' => $tasks->where('last_status', 'failed')->count(),
            'running' => $tasks->where('last_status', 'running')->count(),
            'stale' => $tasks->where('is_stale_running', true)->count(),
            'healthy' => $tasks->where('last_status', 'success')->count(),
            'never' => $tasks->where('last_status', 'never')->count(),
        ];
    }

    #[Computed]
    public function queuedCount(): int
    {
        return $this->stats['pending'] + $this->stats['delayed'] + $this->stats['reserved'];
    }

    #[Computed]
    public function oldestPendingHint(): string
    {
        $oldest = $this->stats['oldest_pending_at'];

        return $oldest
            ? __('Oldest :time', ['time' => $oldest->diffForHumans()])
            : __('Queue is empty.');
    }

    #[Computed]
    public function processingHint(): string
    {
        if ($this->stats['stuck_reserved'] > 0) {
            return __(':count stuck', ['count' => number_format($this->stats['stuck_reserved'])]);
        }

        return __('Reserved by workers');
    }

    #[Computed]
    public function failedHint(): string
    {
        return __(':count in the last 24 hours', [
            'count' => number_format($this->stats['failed_last_24h']),
        ]);
    }

    #[Computed]
    public function hasMoreQueued(): bool
    {
        return $this->queuedCount > $this->queuedJobs->count();
    }

    #[Computed]
    public function hasMoreFailed(): bool
    {
        return $this->stats['failed'] > $this->failedJobs->count();
    }
}
