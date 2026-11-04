<?php

use App\Services\QueueMonitorService;
use App\Services\ScheduledTaskMonitorService;
use App\Traits\InteractsWithTallStackModals;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use TallStackUi\Traits\Interactions;

new class extends Component
{
    use Interactions;
    use InteractsWithTallStackModals;

    public bool $autoRefresh = true;

    public int $pollSeconds = 5;

    #[Url]
    public string $tab = 'pending';

    #[Url]
    public string $queue = '';

    public int $queuedLimit = 25;

    public int $failedLimit = 25;

    public ?int $selectedJobId = null;

    public ?string $selectedFailedUuid = null;

    public ?string $selectedTaskName = null;

    public string $confirmAction = '';

    public string $confirmTarget = '';

    public string $confirmTitle = '';

    public string $confirmMessage = '';

    public string $confirmLabel = '';

    public function mount(): void
    {
        $this->ensureAdmin();

        $this->pollSeconds = max(2, (int) config('picstome.queue_manager.poll_seconds', 5));
        $this->queuedLimit = max(5, (int) config('picstome.queue_manager.page_size', 25));
        $this->failedLimit = $this->queuedLimit;
    }

    public function updatedQueue(): void
    {
        $this->queuedLimit = max(5, (int) config('picstome.queue_manager.page_size', 25));
        $this->failedLimit = $this->queuedLimit;

        $this->forgetData();
    }

    public function refresh(): void
    {
        $this->ensureAdmin();
        $this->forgetData();

        $this->toast()->success(__('Queue refreshed.'))->send();
    }

    public function loadMoreQueued(): void
    {
        $this->queuedLimit += max(5, (int) config('picstome.queue_manager.page_size', 25));

        unset($this->queuedJobs, $this->hasMoreQueued);
    }

    public function loadMoreFailed(): void
    {
        $this->failedLimit += max(5, (int) config('picstome.queue_manager.page_size', 25));

        unset($this->failedJobs, $this->hasMoreFailed);
    }

    public function showJob(int $id): void
    {
        $this->selectedJobId = $id;

        unset($this->selectedJob);

        $this->openModal('job-details');
    }

    public function showFailedJob(string $uuid): void
    {
        $this->selectedFailedUuid = $uuid;

        unset($this->selectedFailedJob);

        $this->openModal('failed-job-details');
    }

    public function showScheduledTask(string $name): void
    {
        $this->selectedTaskName = $name;

        unset($this->selectedScheduledTask);

        $this->openModal('scheduled-task-details');
    }

    public function confirmRetryFailed(string $uuid): void
    {
        $this->prepareConfirmation(
            action: 'retry',
            target: $uuid,
            title: __('Retry failed job?'),
            message: __('The job will be pushed back onto the queue and removed from the failed list.'),
            label: __('Retry'),
        );
    }

    public function confirmRetryAllFailed(): void
    {
        $queue = $this->queue;

        $this->prepareConfirmation(
            action: 'retry-all',
            target: $queue,
            title: $queue !== '' ? __('Retry all failed jobs in :queue?', ['queue' => $queue]) : __('Retry all failed jobs?'),
            message: __('Every failed job will be pushed back onto the queue.'),
            label: __('Retry all'),
        );
    }

    public function confirmForgetFailed(string $uuid): void
    {
        $this->prepareConfirmation(
            action: 'forget',
            target: $uuid,
            title: __('Forget failed job?'),
            message: __('The failed job record will be deleted. This cannot be undone.'),
            label: __('Forget'),
        );
    }

    public function confirmReleaseJob(int $id): void
    {
        $this->prepareConfirmation(
            action: 'release',
            target: (string) $id,
            title: __('Release stuck job?'),
            message: __('The job will be available to a worker again. If it is still running, it could be processed twice.'),
            label: __('Release'),
        );
    }

    public function confirmDeleteJob(int $id): void
    {
        $this->prepareConfirmation(
            action: 'delete',
            target: (string) $id,
            title: __('Delete queued job?'),
            message: __('The job will be removed from the queue. This cannot be undone.'),
            label: __('Delete'),
        );
    }

    public function confirmRestartWorkers(): void
    {
        $this->prepareConfirmation(
            action: 'restart',
            target: '',
            title: __('Restart queue workers?'),
            message: __('Workers will stop after their current job and be restarted by their process manager.'),
            label: __('Restart workers'),
        );
    }

    public function executeConfirmed(): void
    {
        $this->ensureAdmin();

        $service = app(QueueMonitorService::class);
        $action = $this->confirmAction;
        $target = $this->confirmTarget;

        $result = match ($action) {
            'retry' => $service->retryFailed($target),
            'retry-all' => $service->retryAllFailed($target !== '' ? $target : null) > 0,
            'forget' => $service->forgetFailed($target),
            'release' => $service->releaseJob((int) $target),
            'delete' => $service->deleteJob((int) $target),
            'restart' => $service->restartWorkers(),
            default => false,
        };

        $this->closeModal('confirm-action');
        $this->closeModal('job-details');
        $this->closeModal('failed-job-details');
        $this->resetConfirm();
        $this->forgetData();

        if ($result) {
            $this->toast()->success($this->successMessage($action))->send();

            return;
        }

        $this->toast()->error(__('The action could not be completed. Refresh and try again.'))->send();
    }

    public function formatDuration(?float $seconds): string
    {
        if ($seconds === null) {
            return '—';
        }

        if ($seconds < 1) {
            return number_format($seconds * 1000).' ms';
        }

        if ($seconds < 60) {
            return number_format($seconds, 1).' s';
        }

        return gmdate('H:i:s', (int) $seconds);
    }

    public function taskStatusColor(string $status, bool $isStale = false): string
    {
        return match (true) {
            $isStale => 'red',
            $status === 'success' => 'green',
            $status === 'failed' => 'red',
            $status === 'running' => 'amber',
            $status === 'skipped' => 'amber',
            default => 'zinc',
        };
    }

    public function taskStatusLabel(string $status, bool $isStale = false): string
    {
        return match (true) {
            $isStale => __('Stale'),
            $status === 'success' => __('OK'),
            $status === 'failed' => __('Failed'),
            $status === 'running' => __('Running'),
            $status === 'skipped' => __('Skipped'),
            default => __('Never'),
        };
    }

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

    private function prepareConfirmation(
        string $action,
        string $target,
        string $title,
        string $message,
        string $label,
    ): void {
        $this->ensureAdmin();

        $this->confirmAction = $action;
        $this->confirmTarget = $target;
        $this->confirmTitle = $title;
        $this->confirmMessage = $message;
        $this->confirmLabel = $label;

        $this->openModal('confirm-action');
    }

    private function resetConfirm(): void
    {
        $this->confirmAction = '';
        $this->confirmTarget = '';
        $this->confirmTitle = '';
        $this->confirmMessage = '';
        $this->confirmLabel = '';
    }

    private function successMessage(string $action): string
    {
        return match ($action) {
            'retry' => __('Job pushed back onto the queue.'),
            'retry-all' => __('Failed jobs pushed back onto the queue.'),
            'forget' => __('Failed job forgotten.'),
            'release' => __('Job released back onto the queue.'),
            'delete' => __('Job deleted.'),
            'restart' => __('Restart signal sent to the queue workers.'),
            default => __('Done.'),
        };
    }

    private function forgetData(): void
    {
        unset(
            $this->stats,
            $this->queues,
            $this->queuedJobs,
            $this->failedJobs,
            $this->selectedJob,
            $this->selectedFailedJob,
            $this->workerStatus,
            $this->workerLabel,
            $this->workerHint,
            $this->workerCardStatus,
            $this->schedulerStatus,
            $this->schedulerLabel,
            $this->schedulerHint,
            $this->schedulerCardStatus,
            $this->scheduledTasks,
            $this->selectedScheduledTask,
            $this->taskSummary,
            $this->queuedCount,
            $this->oldestPendingHint,
            $this->processingHint,
            $this->failedHint,
            $this->hasMoreQueued,
            $this->hasMoreFailed,
        );
    }

    private function ensureAdmin(): void
    {
        abort_unless(auth()->user()?->is_admin, 403);
    }
}; ?>

<div>
    <div @if ($autoRefresh) wire:poll.{{ $pollSeconds }}s.visible @endif>
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <x-ts-heading size="xl" class="text-brand-green dark:text-brand-cream font-semibold">
                    {{ __('Queue manager') }}
                </x-ts-heading>
                <x-ts-text class="text-brand-ink/70 dark:text-brand-sage/80 mt-1">
                    {{ __('Monitor queue jobs, worker health and scheduled tasks in real time.') }}
                </x-ts-text>
                <x-ts-text class="text-brand-ink/55 dark:text-brand-sage/70 mt-2 text-xs">
                    {{ __('Auto-refresh updates the data every :seconds seconds. Refresh now reloads immediately.', ['seconds' => $pollSeconds]) }}
                </x-ts-text>
            </div>

            <div class="flex flex-wrap items-end gap-3">
                <x-ts-switch
                    wire:model.live="autoRefresh"
                    :label="__('Auto-refresh (:seconds s)', ['seconds' => $pollSeconds])"
                    data-test="auto-refresh-toggle"
                />

                <x-ts-button
                    wire:click="refresh"
                    icon="arrow-path"
                    variant="primary"
                    wire:loading.attr="disabled"
                    class="btn-ink"
                    :title="__('Refresh now')"
                >
                    {{ __('Refresh now') }}
                </x-ts-button>
            </div>
        </div>

        @if ($this->stats['driver'] !== 'database')
            <x-ts-callout variant="warning" icon="exclamation-triangle" class="mt-6">
                <x-ts-callout-heading>{{ __('Detailed monitoring unavailable') }}</x-ts-callout-heading>
                <x-ts-callout-text>
                    {{ __('The queue driver is :driver. Per-job inspection and stuck-job actions require the database queue driver.', ['driver' => $this->stats['driver']]) }}
                </x-ts-callout-text>
            </x-ts-callout>
        @endif

        <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
            <x-queue-status-card
                :label="__('Worker')"
                :value="$this->workerLabel"
                :hint="$this->workerHint"
                :status="$this->workerCardStatus"
                icon="cpu-chip"
            />

            <x-queue-status-card
                :label="__('Scheduler')"
                :value="$this->schedulerLabel"
                :hint="$this->schedulerHint"
                :status="$this->schedulerCardStatus"
                icon="clock"
            />

            <x-queue-status-card
                :label="__('Pending jobs')"
                :value="number_format($this->stats['pending'])"
                :hint="$this->oldestPendingHint"
                status="blue"
                icon="queue-list"
            />

            <x-queue-status-card
                :label="__('Processing')"
                :value="number_format($this->stats['reserved'])"
                :hint="$this->processingHint"
                :status="$this->stats['stuck_reserved'] > 0 ? 'amber' : 'zinc'"
                icon="cog-6-tooth"
            />

            <x-queue-status-card
                :label="__('Failed jobs')"
                :value="number_format($this->stats['failed'])"
                :hint="$this->failedHint"
                :status="$this->stats['failed'] > 0 ? 'red' : 'green'"
                icon="exclamation-triangle"
            />
        </div>

        @if ($this->workerStatus['status'] === 'stopped')
            <x-ts-callout variant="danger" icon="x-circle" class="mt-6" data-test="worker-stopped-alert">
                <x-ts-callout-heading>{{ __('Queue worker is not running') }}</x-ts-callout-heading>
                <x-ts-callout-text>
                    {{ __('No heartbeat has been received recently and there are no reserved jobs. Pending jobs will not be processed until a worker starts.') }}
                </x-ts-callout-text>
            </x-ts-callout>
        @elseif ($this->workerStatus['status'] === 'busy')
            <x-ts-callout variant="warning" icon="exclamation-triangle" class="mt-6">
                <x-ts-callout-heading>{{ __('Worker heartbeat is stale') }}</x-ts-callout-heading>
                <x-ts-callout-text>
                    {{ __('There are reserved jobs but no recent heartbeat. A long-running job may be in progress; check the processing jobs below.') }}
                </x-ts-callout-text>
            </x-ts-callout>
        @endif

        @if ($this->schedulerStatus['status'] !== 'running')
            <x-ts-callout variant="warning" icon="clock" class="mt-6">
                <x-ts-callout-heading>{{ __('Scheduler is not reporting') }}</x-ts-callout-heading>
                <x-ts-callout-text>
                    {{ __('Scheduled tasks will not run until the scheduler cron or schedule:work process is running.') }}
                </x-ts-callout-text>
            </x-ts-callout>
        @endif

        @if ($this->stats['stuck_reserved'] > 0)
            <x-ts-callout variant="warning" icon="exclamation-triangle" class="mt-6" data-test="stuck-jobs-alert">
                <x-ts-callout-heading>{{ __('Jobs may be stuck') }}</x-ts-callout-heading>
                <x-ts-callout-text>
                    {{ __(':count reserved jobs have been processing for longer than the queue retry window. Release them or restart the workers if they are not progressing.', ['count' => number_format($this->stats['stuck_reserved'])]) }}
                </x-ts-callout-text>
            </x-ts-callout>
        @endif

        <section class="mt-8" aria-labelledby="queues-heading">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <x-ts-heading id="queues-heading" size="lg" class="text-brand-green dark:text-brand-cream font-semibold">
                        {{ __('Queues') }}
                    </x-ts-heading>
                    <x-ts-text class="text-brand-ink/70 dark:text-brand-sage/80 mt-1">
                        {{ __('Pending, processing and delayed jobs per queue.') }}
                    </x-ts-text>
                </div>

                <x-ts-select wire:model.live="queue" :label="__('Filter')" class="w-full sm:w-56">
                    <x-ts-select-option value="">{{ __('All queues') }}</x-ts-select-option>
                    @foreach ($this->queues as $queueRow)
                        <x-ts-select-option :value="$queueRow['name']">{{ $queueRow['name'] }}</x-ts-select-option>
                    @endforeach
                </x-ts-select>
            </div>

            <x-ts-card class="mt-4 overflow-hidden p-0!">
                <div class="p-4 sm:p-6">
                    <x-data-table>
                        <x-data-table.columns>
                            <x-data-table.column>{{ __('Queue') }}</x-data-table.column>
                            <x-data-table.column>{{ __('Pending') }}</x-data-table.column>
                        <x-data-table.column>{{ __('Processing') }}</x-data-table.column>
                        <x-data-table.column>{{ __('Delayed') }}</x-data-table.column>
                        <x-data-table.column>{{ __('Oldest pending') }}</x-data-table.column>
                        <x-data-table.column>{{ __('Status') }}</x-data-table.column>
                    </x-data-table.columns>

                    <x-data-table.rows>
                        @forelse ($this->queues as $queueRow)
                            <x-data-table.row :key="$queueRow['name']">
                                <x-data-table.cell variant="strong">{{ $queueRow['name'] }}</x-data-table.cell>
                                <x-data-table.cell>{{ number_format($queueRow['pending']) }}</x-data-table.cell>
                                <x-data-table.cell>{{ number_format($queueRow['reserved']) }}</x-data-table.cell>
                                <x-data-table.cell>{{ number_format($queueRow['delayed']) }}</x-data-table.cell>
                                <x-data-table.cell>
                                    @if ($queueRow['oldest_pending_at'])
                                        <span title="{{ $queueRow['oldest_pending_at']->toDateTimeString() }}">
                                            {{ $queueRow['oldest_pending_at']->diffForHumans() }}
                                        </span>
                                    @else
                                        <span class="text-zinc-400">—</span>
                                    @endif
                                </x-data-table.cell>
                                <x-data-table.cell>
                                    @if ($queueRow['paused'])
                                        <x-ts-badge color="amber" size="sm">{{ __('Paused') }}</x-ts-badge>
                                    @else
                                        <x-ts-badge color="green" size="sm">{{ __('Active queue') }}</x-ts-badge>
                                    @endif
                                </x-data-table.cell>
                            </x-data-table.row>
                        @empty
                            <x-data-table.row>
                                <x-data-table.cell colspan="6">{{ __('No queues found.') }}</x-data-table.cell>
                            </x-data-table.row>
                        @endforelse
                    </x-data-table.rows>
                    </x-data-table>
                </div>
            </x-ts-card>
        </section>

        <section class="mt-8" aria-labelledby="jobs-heading">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <x-ts-heading id="jobs-heading" size="lg" class="text-brand-green dark:text-brand-cream font-semibold">
                        {{ __('Queue jobs') }}
                    </x-ts-heading>
                    <x-ts-text class="text-brand-ink/70 dark:text-brand-sage/80 mt-1">
                        {{ __('Oldest queued jobs first. Failed jobs can be retried from here.') }}
                    </x-ts-text>
                </div>

                <div class="flex flex-wrap gap-2">
                    @if ($this->failedJobs->isNotEmpty())
                        <x-ts-button size="sm" variant="outline" wire:click="confirmRetryAllFailed" icon="arrow-path">
                            {{ __('Retry all failed') }}
                        </x-ts-button>
                    @endif

                    <x-ts-button size="sm" variant="outline" wire:click="confirmRestartWorkers" icon="arrow-path-rounded-square">
                        {{ __('Restart workers') }}
                    </x-ts-button>
                </div>
            </div>

            <x-ts-card class="mt-4 overflow-hidden p-0!">
                <div class="border-brand-green/10 dark:border-white/10 flex gap-1 border-b px-4 sm:px-6">
                    <button
                        type="button"
                        wire:click="$set('tab', 'pending')"
                        data-test="queue-tab-pending"
                        @class([
                            'border-b-2 px-3 py-3 text-sm font-medium transition',
                            'border-brand-green text-brand-green dark:border-brand-sage dark:text-brand-sage' => $tab === 'pending',
                            'border-transparent text-zinc-500 hover:text-zinc-800 dark:text-zinc-400 dark:hover:text-white' => $tab !== 'pending',
                        ])
                    >
                        {{ __('Queued (:count)', ['count' => number_format($this->queuedCount)]) }}
                    </button>

                    <button
                        type="button"
                        wire:click="$set('tab', 'failed')"
                        data-test="queue-tab-failed"
                        @class([
                            'border-b-2 px-3 py-3 text-sm font-medium transition',
                            'border-brand-green text-brand-green dark:border-brand-sage dark:text-brand-sage' => $tab === 'failed',
                            'border-transparent text-zinc-500 hover:text-zinc-800 dark:text-zinc-400 dark:hover:text-white' => $tab !== 'failed',
                        ])
                    >
                        {{ __('Failed (:count)', ['count' => number_format($this->stats['failed'])]) }}
                    </button>
                </div>

                <div class="p-4 sm:p-6">
                    @if ($tab === 'pending')
                        <x-data-table>
                            <x-data-table.columns>
                                <x-data-table.column>{{ __('Job') }}</x-data-table.column>
                                <x-data-table.column>{{ __('Queue') }}</x-data-table.column>
                                <x-data-table.column>{{ __('Attempts') }}</x-data-table.column>
                                <x-data-table.column>{{ __('State') }}</x-data-table.column>
                                <x-data-table.column>{{ __('Age') }}</x-data-table.column>
                                <x-data-table.column class="group/right-align">{{ __('Actions') }}</x-data-table.column>
                            </x-data-table.columns>

                            <x-data-table.rows>
                                @forelse ($this->queuedJobs as $job)
                                    <x-data-table.row :key="$job['id']">
                                        <x-data-table.cell variant="strong">
                                            <div class="flex max-w-[18rem] flex-col">
                                                <span class="truncate">{{ $job['name'] }}</span>
                                                @if ($job['class'] !== $job['name'])
                                                    <span class="truncate text-xs font-normal text-zinc-500 dark:text-zinc-400">{{ $job['class'] }}</span>
                                                @endif
                                            </div>
                                        </x-data-table.cell>
                                        <x-data-table.cell>{{ $job['queue'] }}</x-data-table.cell>
                                        <x-data-table.cell>
                                            {{ $job['attempts'] }}@if ($job['max_tries']) / {{ $job['max_tries'] }}@endif
                                        </x-data-table.cell>
                                        <x-data-table.cell>
                                            @if ($job['is_stuck'])
                                                <x-ts-badge color="red" size="sm">{{ __('Stuck') }}</x-ts-badge>
                                            @elseif ($job['reserved_at'])
                                                <x-ts-badge color="amber" size="sm">{{ __('Processing') }}</x-ts-badge>
                                            @elseif ($job['is_delayed'])
                                                <x-ts-badge color="blue" size="sm">{{ __('Delayed') }}</x-ts-badge>
                                            @elseif ($job['is_old'])
                                                <x-ts-badge color="amber" size="sm">{{ __('Waiting') }}</x-ts-badge>
                                            @else
                                                <x-ts-badge color="green" size="sm">{{ __('Pending') }}</x-ts-badge>
                                            @endif
                                        </x-data-table.cell>
                                        <x-data-table.cell>
                                            <span title="{{ $job['created_at']->toDateTimeString() }}">
                                                {{ $job['created_at']->diffForHumans() }}
                                            </span>
                                        </x-data-table.cell>
                                        <x-data-table.cell class="text-end">
                                            <div class="flex justify-end gap-1">
                                                <x-ts-button
                                                    size="xs"
                                                    variant="ghost"
                                                    icon="eye"
                                                    wire:click="showJob({{ $job['id'] }})"
                                                    data-test="queued-job-details"
                                                    :title="__('Details')"
                                                    :aria-label="__('Details')"
                                                >
                                                    <span class="hidden sm:inline">{{ __('Details') }}</span>
                                                </x-ts-button>

                                                @if ($job['is_stuck'])
                                                    <x-ts-button
                                                        size="xs"
                                                        variant="ghost"
                                                        icon="arrow-path"
                                                        wire:click="confirmReleaseJob({{ $job['id'] }})"
                                                        :title="__('Release')"
                                                        :aria-label="__('Release')"
                                                    >
                                                        <span class="hidden sm:inline">{{ __('Release') }}</span>
                                                    </x-ts-button>
                                                @endif

                                                <x-ts-button
                                                    size="xs"
                                                    variant="ghost"
                                                    icon="trash"
                                                    wire:click="confirmDeleteJob({{ $job['id'] }})"
                                                    :title="__('Delete')"
                                                    :aria-label="__('Delete')"
                                                >
                                                    <span class="hidden sm:inline">{{ __('Delete') }}</span>
                                                </x-ts-button>
                                            </div>
                                        </x-data-table.cell>
                                    </x-data-table.row>
                                @empty
                                    <x-data-table.row>
                                        <x-data-table.cell colspan="6">{{ __('No queued jobs.') }}</x-data-table.cell>
                                    </x-data-table.row>
                                @endforelse
                            </x-data-table.rows>
                        </x-data-table>

                        @if ($this->hasMoreQueued)
                            <div class="flex justify-center pt-4">
                                <x-ts-button size="sm" variant="outline" wire:click="loadMoreQueued">
                                    {{ __('Load more') }}
                                </x-ts-button>
                            </div>
                        @endif
                    @else
                        <x-data-table>
                            <x-data-table.columns>
                                <x-data-table.column>{{ __('Job') }}</x-data-table.column>
                                <x-data-table.column>{{ __('Queue') }}</x-data-table.column>
                                <x-data-table.column>{{ __('Failed') }}</x-data-table.column>
                                <x-data-table.column class="group/right-align">{{ __('Actions') }}</x-data-table.column>
                            </x-data-table.columns>

                            <x-data-table.rows>
                                @forelse ($this->failedJobs as $job)
                                    <x-data-table.row :key="$job['uuid']">
                                        <x-data-table.cell variant="strong">
                                            <div class="flex max-w-[22rem] flex-col gap-0.5">
                                                <span class="truncate">{{ $job['name'] }}</span>
                                                @if ($job['class'] !== $job['name'])
                                                    <span class="truncate text-xs font-normal text-zinc-500 dark:text-zinc-400">{{ $job['class'] }}</span>
                                                @endif
                                                <span class="truncate text-xs font-normal text-red-600 dark:text-red-300" title="{{ $job['exception_summary'] }}">
                                                    {{ $job['exception_summary'] }}
                                                </span>
                                            </div>
                                        </x-data-table.cell>
                                        <x-data-table.cell>{{ $job['queue'] }}</x-data-table.cell>
                                        <x-data-table.cell>
                                            <span title="{{ $job['failed_at']->toDateTimeString() }}">
                                                {{ $job['failed_at']->diffForHumans() }}
                                            </span>
                                        </x-data-table.cell>
                                        <x-data-table.cell class="text-end">
                                            <div class="flex justify-end gap-1">
                                                <x-ts-button
                                                    size="xs"
                                                    variant="ghost"
                                                    icon="eye"
                                                    wire:click="showFailedJob('{{ $job['uuid'] }}')"
                                                    data-test="failed-job-details"
                                                    :title="__('Details')"
                                                    :aria-label="__('Details')"
                                                >
                                                    <span class="hidden sm:inline">{{ __('Details') }}</span>
                                                </x-ts-button>

                                                <x-ts-button
                                                    size="xs"
                                                    variant="ghost"
                                                    icon="arrow-path"
                                                    wire:click="confirmRetryFailed('{{ $job['uuid'] }}')"
                                                    data-test="failed-job-retry"
                                                    :title="__('Retry')"
                                                    :aria-label="__('Retry')"
                                                >
                                                    <span class="hidden sm:inline">{{ __('Retry') }}</span>
                                                </x-ts-button>

                                                <x-ts-button
                                                    size="xs"
                                                    variant="ghost"
                                                    icon="trash"
                                                    wire:click="confirmForgetFailed('{{ $job['uuid'] }}')"
                                                    data-test="failed-job-forget"
                                                    :title="__('Forget')"
                                                    :aria-label="__('Forget')"
                                                >
                                                    <span class="hidden sm:inline">{{ __('Forget') }}</span>
                                                </x-ts-button>
                                            </div>
                                        </x-data-table.cell>
                                    </x-data-table.row>
                                @empty
                                    <x-data-table.row>
                                        <x-data-table.cell colspan="4">{{ __('No failed jobs.') }}</x-data-table.cell>
                                    </x-data-table.row>
                                @endforelse
                            </x-data-table.rows>
                        </x-data-table>

                        @if ($this->hasMoreFailed)
                            <div class="flex justify-center pt-4">
                                <x-ts-button size="sm" variant="outline" wire:click="loadMoreFailed">
                                    {{ __('Load more') }}
                                </x-ts-button>
                            </div>
                        @endif
                    @endif
                </div>
            </x-ts-card>
        </section>

        <section class="mt-8" aria-labelledby="scheduled-heading">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <x-ts-heading id="scheduled-heading" size="lg" class="text-brand-green dark:text-brand-cream font-semibold">
                        {{ __('Scheduled tasks') }}
                    </x-ts-heading>
                    <x-ts-text class="text-brand-ink/70 dark:text-brand-sage/80 mt-1">
                        {{ __('Last run and next run of the application scheduler. Read-only.') }}
                    </x-ts-text>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <x-ts-badge :color="$this->schedulerCardStatus">
                        {{ __('Scheduler :status', ['status' => $this->schedulerLabel]) }}
                    </x-ts-badge>

                    <x-ts-badge color="zinc">
                        {{ __(':count tasks', ['count' => number_format($this->taskSummary['total'])]) }}
                    </x-ts-badge>

                    @if ($this->taskSummary['failed'] > 0)
                        <x-ts-badge color="red">
                            {{ __(':count failed', ['count' => number_format($this->taskSummary['failed'])]) }}
                        </x-ts-badge>
                    @endif

                    @if ($this->taskSummary['running'] > 0)
                        <x-ts-badge color="amber">
                            {{ __(':count running', ['count' => number_format($this->taskSummary['running'])]) }}
                        </x-ts-badge>
                    @endif

                    @if ($this->taskSummary['stale'] > 0)
                        <x-ts-badge color="red">
                            {{ __(':count stale', ['count' => number_format($this->taskSummary['stale'])]) }}
                        </x-ts-badge>
                    @endif
                </div>
            </div>

            <x-ts-card class="mt-4 overflow-hidden p-0!">
                <div class="p-4 sm:p-6">
                    <x-data-table>
                        <x-data-table.columns>
                            <x-data-table.column>{{ __('Task') }}</x-data-table.column>
                            <x-data-table.column>{{ __('Frequency') }}</x-data-table.column>
                        <x-data-table.column>{{ __('Last run') }}</x-data-table.column>
                        <x-data-table.column>{{ __('Status') }}</x-data-table.column>
                        <x-data-table.column class="max-lg:hidden">{{ __('Duration') }}</x-data-table.column>
                        <x-data-table.column>{{ __('Next run') }}</x-data-table.column>
                        <x-data-table.column class="group/right-align">{{ __('Actions') }}</x-data-table.column>
                    </x-data-table.columns>

                    <x-data-table.rows>
                        @forelse ($this->scheduledTasks as $task)
                            <x-data-table.row :key="$task['name']">
                                <x-data-table.cell variant="strong">
                                    <div class="flex max-w-[20rem] flex-col">
                                        <span class="truncate">{{ $task['name'] }}</span>
                                        <span class="truncate text-xs font-normal text-zinc-500 dark:text-zinc-400">{{ $task['command'] }}</span>
                                    </div>
                                </x-data-table.cell>

                                <x-data-table.cell>
                                    <div class="flex flex-col">
                                        <span>{{ $task['frequency'] }}</span>
                                        <code class="text-xs text-zinc-500 dark:text-zinc-400">{{ $task['expression'] }}</code>
                                    </div>
                                </x-data-table.cell>

                                <x-data-table.cell>
                                    @if ($task['last_status'] === 'never')
                                        <span class="text-zinc-500 dark:text-zinc-400">{{ __('Never') }}</span>
                                    @elseif ($task['last_status'] === 'running')
                                        <span>{{ __('Running since :time', ['time' => $task['last_started_at']?->diffForHumans() ?? __('unknown')]) }}</span>
                                    @elseif ($task['last_status'] === 'skipped')
                                        <span>{{ __('Skipped :time', ['time' => $task['last_skipped_at']?->diffForHumans() ?? __('unknown')]) }}</span>
                                    @else
                                        <span title="{{ $task['last_finished_at']?->toDateTimeString() }}">
                                            {{ $task['last_finished_at']?->diffForHumans() ?? __('Never') }}
                                        </span>
                                    @endif
                                </x-data-table.cell>

                                <x-data-table.cell>
                                    <x-ts-badge :color="$this->taskStatusColor($task['last_status'], $task['is_stale_running'])" size="sm">
                                        {{ $this->taskStatusLabel($task['last_status'], $task['is_stale_running']) }}
                                    </x-ts-badge>
                                </x-data-table.cell>

                                <x-data-table.cell class="max-lg:hidden">
                                    {{ $task['last_status'] === 'running' ? '—' : $this->formatDuration($task['last_runtime']) }}
                                </x-data-table.cell>

                                <x-data-table.cell>
                                    @if ($task['next_run_at'])
                                        <span title="{{ $task['next_run_at']->toDateTimeString() }}">
                                            {{ $task['next_run_at']->diffForHumans() }}
                                        </span>
                                    @else
                                        <span class="text-zinc-400">—</span>
                                    @endif
                                </x-data-table.cell>

                                <x-data-table.cell class="text-end">
                                    <x-ts-button
                                        size="xs"
                                        variant="ghost"
                                        icon="eye"
                                        wire:click="showScheduledTask('{{ $task['name'] }}')"
                                        data-test="scheduled-task-details-{{ \Illuminate\Support\Str::slug($task['name']) }}"
                                        :title="__('Details')"
                                        :aria-label="__('Details')"
                                    >
                                        <span class="hidden sm:inline">{{ __('Details') }}</span>
                                    </x-ts-button>
                                </x-data-table.cell>
                            </x-data-table.row>
                        @empty
                            <x-data-table.row>
                                <x-data-table.cell colspan="7">{{ __('No scheduled tasks found.') }}</x-data-table.cell>
                            </x-data-table.row>
                        @endforelse
                    </x-data-table.rows>
                    </x-data-table>
                </div>
            </x-ts-card>
        </section>
    </div>

    <x-ts-modal name="job-details" class="w-full max-w-3xl">
        @if ($this->selectedJob)
            @php($job = $this->selectedJob)

            <div class="space-y-5">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <x-ts-heading size="lg">{{ $job['name'] }}</x-ts-heading>
                        <x-ts-text class="text-sm text-zinc-500 dark:text-zinc-400">{{ $job['class'] }}</x-ts-text>
                    </div>

                    @if ($job['is_stuck'])
                        <x-ts-badge color="red">{{ __('Stuck') }}</x-ts-badge>
                    @elseif ($job['reserved_at'])
                        <x-ts-badge color="amber">{{ __('Processing') }}</x-ts-badge>
                    @elseif ($job['is_delayed'])
                        <x-ts-badge color="blue">{{ __('Delayed') }}</x-ts-badge>
                    @else
                        <x-ts-badge color="green">{{ __('Pending') }}</x-ts-badge>
                    @endif
                </div>

                <dl class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <dt class="text-xs font-semibold tracking-wide text-zinc-500 uppercase dark:text-zinc-400">{{ __('Queue') }}</dt>
                        <dd class="mt-1 text-sm font-medium">{{ $job['queue'] }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold tracking-wide text-zinc-500 uppercase dark:text-zinc-400">{{ __('Attempts') }}</dt>
                        <dd class="mt-1 text-sm font-medium">{{ $job['attempts'] }}@if ($job['max_tries']) / {{ $job['max_tries'] }}@endif</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold tracking-wide text-zinc-500 uppercase dark:text-zinc-400">{{ __('Created') }}</dt>
                        <dd class="mt-1 text-sm font-medium">{{ $job['created_at']->toDateTimeString() }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold tracking-wide text-zinc-500 uppercase dark:text-zinc-400">{{ __('Available') }}</dt>
                        <dd class="mt-1 text-sm font-medium">{{ $job['available_at']->toDateTimeString() }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold tracking-wide text-zinc-500 uppercase dark:text-zinc-400">{{ __('Reserved') }}</dt>
                        <dd class="mt-1 text-sm font-medium">{{ $job['reserved_at']?->toDateTimeString() ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold tracking-wide text-zinc-500 uppercase dark:text-zinc-400">{{ __('Timeout') }}</dt>
                        <dd class="mt-1 text-sm font-medium">{{ $job['timeout'] ? __(':seconds seconds', ['seconds' => $job['timeout']]) : '—' }}</dd>
                    </div>
                </dl>

                <div>
                    <x-ts-heading size="sm">{{ __('Payload') }}</x-ts-heading>
                    <pre class="mt-2 max-h-96 overflow-auto rounded-lg bg-zinc-950 p-4 text-xs text-zinc-100">{{ $job['payload_pretty'] }}</pre>
                </div>

                <div class="flex justify-end gap-2">
                    @if ($job['is_stuck'])
                        <x-ts-button size="sm" variant="outline" icon="arrow-path" wire:click="confirmReleaseJob({{ $job['id'] }})">
                            {{ __('Release') }}
                        </x-ts-button>
                    @endif

                    <x-ts-button size="sm" variant="outline" icon="trash" wire:click="confirmDeleteJob({{ $job['id'] }})">
                        {{ __('Delete') }}
                    </x-ts-button>

                    <x-ts-modal-close>
                        <x-ts-button size="sm" variant="outline">{{ __('Close') }}</x-ts-button>
                    </x-ts-modal-close>
                </div>
            </div>
        @endif
    </x-ts-modal>

    <x-ts-modal name="failed-job-details" class="w-full max-w-3xl">
        @if ($this->selectedFailedJob)
            @php($job = $this->selectedFailedJob)

            <div class="space-y-5">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <x-ts-heading size="lg">{{ $job['name'] }}</x-ts-heading>
                        <x-ts-text class="text-sm text-zinc-500 dark:text-zinc-400">{{ $job['class'] }}</x-ts-text>
                    </div>

                    <x-ts-badge color="red">{{ __('Failed') }}</x-ts-badge>
                </div>

                <dl class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <dt class="text-xs font-semibold tracking-wide text-zinc-500 uppercase dark:text-zinc-400">{{ __('Queue') }}</dt>
                        <dd class="mt-1 text-sm font-medium">{{ $job['queue'] }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold tracking-wide text-zinc-500 uppercase dark:text-zinc-400">{{ __('Connection') }}</dt>
                        <dd class="mt-1 text-sm font-medium">{{ $job['connection'] }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold tracking-wide text-zinc-500 uppercase dark:text-zinc-400">{{ __('Failed at') }}</dt>
                        <dd class="mt-1 text-sm font-medium">{{ $job['failed_at']->toDateTimeString() }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold tracking-wide text-zinc-500 uppercase dark:text-zinc-400">{{ __('UUID') }}</dt>
                        <dd class="mt-1 font-mono text-xs">{{ $job['uuid'] }}</dd>
                    </div>
                </dl>

                <div>
                    <x-ts-heading size="sm">{{ __('Exception') }}</x-ts-heading>
                    <pre class="mt-2 max-h-96 overflow-auto rounded-lg bg-zinc-950 p-4 text-xs text-zinc-100">{{ $job['exception_full'] }}</pre>
                </div>

                <div>
                    <x-ts-heading size="sm">{{ __('Payload') }}</x-ts-heading>
                    <pre class="mt-2 max-h-96 overflow-auto rounded-lg bg-zinc-950 p-4 text-xs text-zinc-100">{{ $job['payload_pretty'] }}</pre>
                </div>

                <div class="flex justify-end gap-2">
                    <x-ts-button size="sm" variant="outline" icon="arrow-path" wire:click="confirmRetryFailed('{{ $job['uuid'] }}')">
                        {{ __('Retry') }}
                    </x-ts-button>

                    <x-ts-button size="sm" variant="outline" icon="trash" wire:click="confirmForgetFailed('{{ $job['uuid'] }}')">
                        {{ __('Forget') }}
                    </x-ts-button>

                    <x-ts-modal-close>
                        <x-ts-button size="sm" variant="outline">{{ __('Close') }}</x-ts-button>
                    </x-ts-modal-close>
                </div>
            </div>
        @endif
    </x-ts-modal>

    <x-ts-modal name="scheduled-task-details" class="w-full max-w-3xl">
        @if ($this->selectedScheduledTask)
            @php($task = $this->selectedScheduledTask)

            <div class="space-y-5">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <x-ts-heading size="lg">{{ $task['name'] }}</x-ts-heading>
                        <x-ts-text class="text-sm text-zinc-500 dark:text-zinc-400">{{ $task['command'] }}</x-ts-text>
                    </div>

                    <x-ts-badge :color="$this->taskStatusColor($task['last_status'], $task['is_stale_running'])">
                        {{ $this->taskStatusLabel($task['last_status'], $task['is_stale_running']) }}
                    </x-ts-badge>
                </div>

                <dl class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <dt class="text-xs font-semibold tracking-wide text-zinc-500 uppercase dark:text-zinc-400">{{ __('Frequency') }}</dt>
                        <dd class="mt-1 text-sm font-medium">{{ $task['frequency'] }} <code class="text-xs text-zinc-500">{{ $task['expression'] }}</code></dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold tracking-wide text-zinc-500 uppercase dark:text-zinc-400">{{ __('Last run') }}</dt>
                        <dd class="mt-1 text-sm font-medium">{{ $task['last_finished_at']?->toDateTimeString() ?? __('Never') }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold tracking-wide text-zinc-500 uppercase dark:text-zinc-400">{{ __('Duration') }}</dt>
                        <dd class="mt-1 text-sm font-medium">{{ $this->formatDuration($task['last_runtime']) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold tracking-wide text-zinc-500 uppercase dark:text-zinc-400">{{ __('Next run') }}</dt>
                        <dd class="mt-1 text-sm font-medium">{{ $task['next_run_at']?->toDateTimeString() ?? '—' }}</dd>
                    </div>
                </dl>

                @if ($task['last_exception'])
                    <div>
                        <x-ts-heading size="sm">{{ __('Last error') }}</x-ts-heading>
                        <pre class="mt-2 max-h-96 overflow-auto rounded-lg bg-zinc-950 p-4 text-xs text-zinc-100">{{ $task['last_exception'] }}</pre>
                    </div>
                @else
                    <x-ts-text class="text-sm text-zinc-500 dark:text-zinc-400">
                        {{ __('No error recorded.') }}
                    </x-ts-text>
                @endif

                <div class="flex justify-end">
                    <x-ts-modal-close>
                        <x-ts-button size="sm" variant="outline">{{ __('Close') }}</x-ts-button>
                    </x-ts-modal-close>
                </div>
            </div>
        @endif
    </x-ts-modal>

    <x-confirm-modal
        name="confirm-action"
        :title="$confirmTitle"
        :message="$confirmMessage"
        :confirm-label="$confirmLabel"
        method="executeConfirmed"
    />
</div>
