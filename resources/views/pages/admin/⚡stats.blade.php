<?php

use App\Services\AdminUsageStatsService;
use App\Traits\FormatsFileSize;
use Carbon\Carbon;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

new class extends Component
{
    use FormatsFileSize;

    #[Url]
    public string $preset = 'current_month';

    public array $customRange = [];

    public function mount(): void
    {
        $this->customRange = [
            'start' => now()->startOfMonth()->toDateString(),
            'end' => now()->toDateString(),
        ];
    }

    public function updatedPreset(): void
    {
        unset($this->rangeStats);
    }

    public function updatedCustomRange(): void
    {
        unset($this->rangeStats);
    }

    #[Computed]
    public function snapshot(): array
    {
        return app(AdminUsageStatsService::class)->snapshot();
    }

    #[Computed]
    public function rangeStats(): array
    {
        [$from, $to] = $this->period();

        return app(AdminUsageStatsService::class)->range($from, $to);
    }

    #[Computed]
    public function periodLabel(): string
    {
        [$from, $to] = $this->period();

        return $from
            ? $from->isoFormat('ll').' – '.$to->isoFormat('ll')
            : __('All time');
    }

    #[Computed]
    public function updatedLabel(): string
    {
        return Carbon::parse($this->snapshot['computed_at'])->diffForHumans();
    }

    /**
     * @return array<string, string>
     */
    #[Computed]
    public function chartTitles(): array
    {
        return [
            'photos' => __('Photos'),
            'galleries' => __('Galleries'),
            'contracts' => __('Contracts'),
            'users' => __('Users'),
            'paid_users' => __('Paid users'),
            'lifetime_users' => __('Lifetime users'),
        ];
    }

    /**
     * Flux modal content is ignored by Livewire after its first render, so
     * every series is prepared server-side up front and swapped with Alpine.
     *
     * @return array<string, list<array{month: string, label: string, value: int}>>
     */
    #[Computed]
    public function chartSeries(): array
    {
        return app(AdminUsageStatsService::class)->monthlySeriesForAll(
            now()->subMonths(11)->startOfMonth(),
            now()->endOfDay(),
        );
    }

    public function refreshStats(): void
    {
        [$from, $to] = $this->period();

        $service = app(AdminUsageStatsService::class);
        $service->forgetSnapshot();
        $service->forgetRange($from, $to);

        unset($this->snapshot, $this->rangeStats, $this->updatedLabel);

        Flux::toast(__('Usage stats refreshed.'));
    }

    /**
     * @return array{0: ?Carbon, 1: Carbon}
     */
    private function period(): array
    {
        $now = now();

        return match ($this->preset) {
            'last_30_days' => [$now->copy()->subDays(29)->startOfDay(), $now->copy()->endOfDay()],
            'last_90_days' => [$now->copy()->subDays(89)->startOfDay(), $now->copy()->endOfDay()],
            'last_year' => [$now->copy()->subYear()->startOfDay(), $now->copy()->endOfDay()],
            'always' => [null, $now->copy()->endOfDay()],
            'custom' => $this->customPeriod(),
            default => [$now->copy()->startOfMonth(), $now->copy()->endOfDay()],
        };
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function customPeriod(): array
    {
        $start = Carbon::parse($this->customRange['start'] ?? now()->startOfMonth())->startOfDay();
        $end = Carbon::parse($this->customRange['end'] ?? now())->endOfDay();

        if ($end->lt($start)) {
            [$start, $end] = [$end->copy()->startOfDay(), $start->copy()->endOfDay()];
        }

        return [$start, $end];
    }
}; ?>

<div x-data="{ chart: null, titles: @js($this->chartTitles) }">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl" class="text-brand-green dark:text-brand-cream font-semibold">
                {{ __('Usage stats') }}
            </flux:heading>
            <flux:text class="text-brand-ink/70 dark:text-brand-sage/80 mt-1">
                {{ __('Photos, storage, and growth across Picstome.') }}
            </flux:text>
        </div>

        <div class="flex flex-wrap items-end gap-3">
            <flux:select wire:model.live="preset" :label="__('Period')" class="w-full sm:w-52">
                <flux:select.option value="current_month">{{ __('Current month') }}</flux:select.option>
                <flux:select.option value="last_30_days">{{ __('Last 30 days') }}</flux:select.option>
                <flux:select.option value="last_90_days">{{ __('Last 90 days') }}</flux:select.option>
                <flux:select.option value="last_year">{{ __('Last year') }}</flux:select.option>
                <flux:select.option value="always">{{ __('Always') }}</flux:select.option>
                <flux:select.option value="custom">{{ __('Custom range') }}</flux:select.option>
            </flux:select>

            @if ($preset === 'custom')
                <flux:date-picker
                    mode="range"
                    wire:model.live="customRange"
                    :label="__('Date range')"
                    class="w-full sm:w-64"
                />
            @endif

            <flux:button
                wire:click="refreshStats"
                icon="arrow-path"
                variant="primary"
                wire:loading.attr="disabled"
                class="btn-ink"
            >
                {{ __('Refresh') }}
            </flux:button>
        </div>
    </div>

    <div class="mt-8 flex flex-wrap items-end justify-between gap-2">
        <div>
            <flux:heading size="lg" class="text-brand-green dark:text-brand-cream font-semibold">
                {{ __('New in period') }}
            </flux:heading>

            <flux:text class="text-brand-ink/70 dark:text-brand-sage/80 mt-1">{{ $this->periodLabel }}</flux:text>
        </div>
    </div>

    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
        <x-stat-card
            :label="__('Photos')"
            :value="number_format($this->rangeStats['photos'])"
            data-test="photos-card"
            tone="ink"
            clickable
            x-on:click="chart = 'photos'; $flux.modal('usage-chart').show()"
        />

        <x-stat-card
            :label="__('Galleries')"
            :value="number_format($this->rangeStats['galleries'])"
            tone="ink"
            clickable
            x-on:click="chart = 'galleries'; $flux.modal('usage-chart').show()"
        />

        <x-stat-card
            :label="__('Contracts')"
            :value="number_format($this->rangeStats['contracts'])"
            tone="ink"
            clickable
            x-on:click="chart = 'contracts'; $flux.modal('usage-chart').show()"
        />

        <x-stat-card
            :label="__('Users')"
            :value="number_format($this->rangeStats['users'])"
            tone="ink"
            clickable
            x-on:click="chart = 'users'; $flux.modal('usage-chart').show()"
        />

        <x-stat-card
            :label="__('Paid users')"
            :value="number_format($this->rangeStats['paid_users'])"
            :hint="__('Subscription started')"
            tone="ink"
            clickable
            x-on:click="chart = 'paid_users'; $flux.modal('usage-chart').show()"
        />

        <x-stat-card
            :label="__('Lifetime users')"
            :value="number_format($this->rangeStats['lifetime_users'])"
            :hint="__('Purchased or granted')"
            tone="ink"
            clickable
            x-on:click="chart = 'lifetime_users'; $flux.modal('usage-chart').show()"
        />
    </div>

    <div class="mt-8 flex flex-wrap items-end justify-between gap-2">
        <flux:heading size="lg" class="text-brand-green dark:text-brand-cream font-semibold">
            {{ __('Current totals') }}
        </flux:heading>

        <div class="text-brand-ink/70 dark:text-brand-sage/80 flex items-center gap-2 text-sm font-medium">
            <flux:icon.clock class="size-4" />
            <span>{{ __('Updated :time', ['time' => $this->updatedLabel]) }}</span>
        </div>
    </div>

    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
        <x-stat-card
            :label="__('Photos stored')"
            :value="number_format($this->snapshot['photos'])"
            :hint="__('Current photo files')"
            tone="green"
        />

        <x-stat-card
            :label="__('S3 storage used')"
            :value="$this->formatFileSize($this->snapshot['storage_bytes'])"
            :hint="__('Includes retained versions')"
            tone="green"
        />

        <x-stat-card :label="__('Galleries')" :value="number_format($this->snapshot['galleries'])" tone="green" />

        <x-stat-card :label="__('Contracts')" :value="number_format($this->snapshot['contracts'])" tone="green" />

        <x-stat-card :label="__('Users')" :value="number_format($this->snapshot['users'])" tone="green" />
    </div>

    <div class="mt-8 flex flex-wrap items-end justify-between gap-2">
        <div>
            <flux:heading size="lg" class="text-brand-green dark:text-brand-cream font-semibold">
                {{ __('Average photos per user') }}
            </flux:heading>

            <flux:text class="text-brand-ink/70 dark:text-brand-sage/80 mt-1">
                {{ __('Paid users have an active subscription. Lifetime includes purchased plans and admin grants.') }}
            </flux:text>
        </div>
    </div>

    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-stat-card
            :label="__('Users')"
            :value="number_format($this->snapshot['avg_photos_per_user'], 1)"
            :hint="__(':count users', ['count' => number_format($this->snapshot['users'])])"
        />

        <x-stat-card
            :label="__('Free users')"
            :value="number_format($this->snapshot['avg_photos_per_free_user'], 1)"
            :hint="__(':count free users', ['count' => number_format($this->snapshot['free_users'])])"
        />

        <x-stat-card
            :label="__('Paid')"
            :value="number_format($this->snapshot['avg_photos_per_paid_user'], 1)"
            :hint="__(':count paid subscribers', ['count' => number_format($this->snapshot['paid_users'])])"
        />

        <x-stat-card
            :label="__('Lifetime users')"
            :value="number_format($this->snapshot['avg_photos_per_lifetime_user'], 1)"
            :hint="__(':count lifetime users', ['count' => number_format($this->snapshot['lifetime_users'])])"
        />
    </div>

    <flux:modal name="usage-chart" class="w-full max-w-3xl">
        <div class="space-y-6" data-test="usage-chart-content">
            <div>
                <flux:heading size="lg" x-text="titles[chart] ?? ''"></flux:heading>

                <flux:text class="mt-1">
                    {{ __('Last 12 months, including the current month.') }}
                </flux:text>
            </div>

            @foreach ($this->chartSeries as $metric => $series)
                <template x-if="chart === @js($metric)">
                    <div>
                        <x-usage-chart :series="$series" :label="$this->chartTitles[$metric]" />
                    </div>
                </template>
            @endforeach
        </div>
    </flux:modal>
</div>
