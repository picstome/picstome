<?php

use App\Traits\InteractsWithTallStackModals;
use App\Traits\Queue\FormatsQueueMetrics;
use App\Traits\Queue\ManagesQueueActions;
use App\Traits\Queue\MonitorsQueueHealth;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use TallStackUi\Traits\Interactions;

new class extends Component
{
    use FormatsQueueMetrics;
    use Interactions;
    use InteractsWithTallStackModals;
    use ManagesQueueActions;
    use MonitorsQueueHealth;

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

    #[Locked]
    public string $confirmAction = '';

    #[Locked]
    public string $confirmTarget = '';

    #[Locked]
    public string $confirmTitle = '';

    #[Locked]
    public string $confirmMessage = '';

    #[Locked]
    public string $confirmLabel = '';
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

        @include('pages.admin.queue.partials.status-overview')

        @include('pages.admin.queue.partials.jobs')
    </div>

    @include('pages.admin.queue.partials.modals')
</div>
