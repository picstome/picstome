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
