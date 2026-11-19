
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

                    @if ($this->stats['failed'] > 0)
                        <x-ts-button
                            size="sm"
                            variant="danger"
                            wire:click="confirmFlushFailed"
                            icon="trash"
                            data-test="delete-all-failed"
                        >
                            {{ __('Delete all failed') }}
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
