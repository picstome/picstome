<?php

namespace App\Traits\Queue;

use App\Services\QueueMonitorService;

/**
 * Queue job actions and the confirmation flow for destructive operations.
 */
trait ManagesQueueActions
{
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

    public function confirmFlushFailed(): void
    {
        $this->prepareConfirmation(
            action: 'flush-failed',
            target: '',
            title: __('Delete all failed jobs?'),
            message: __('This permanently deletes all :count failed jobs. This cannot be undone.', [
                'count' => number_format($this->stats['failed']),
            ]),
            label: __('Delete all'),
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
            'flush-failed' => $service->flushFailed() > 0,
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
            'flush-failed' => __('All failed jobs deleted.'),
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
}
