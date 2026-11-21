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
