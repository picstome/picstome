<?php

use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Laravel\Cashier\Cashier;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    #[Computed]
    public function birthdaySoonCustomers()
    {
        return $this->team->customers()
            ->get()
            ->filter(fn ($customer) => $customer->isBirthdaySoon())
            ->sortBy(function ($customer) {
                $now = now()->startOfDay();
                $thisYearBirthday = $customer->birthdate->copy()->year($now->year);
                if ($thisYearBirthday->lt($now)) {
                    $thisYearBirthday->addYear();
                }

                return $now->diffInDays($thisYearBirthday, false);
            });
    }

    #[Computed]
    public function customersCount()
    {
        return $this->team->customers()->count();
    }

    #[Computed]
    public function expiringGalleries()
    {
        return $this->team->galleries()
            ->whereNotNull('expiration_date')
            ->where('expiration_date', '>=', now()->copy()->startOfDay())
            ->where('expiration_date', '<=', now()->addDays(7)->toDateString())
            ->orderBy('expiration_date')
            ->get();
    }

    #[Computed]
    public function galleriesCount()
    {
        return $this->team->galleries()->count();
    }

    #[Computed]
    public function incompleteSteps()
    {
        $dismissed = $this->team->dismissed_setup_steps ?? [];

        return collect($this->steps)
            ->where('complete', false)
            ->reject(fn ($step) => in_array($step['key'], $dismissed));
    }

    #[Computed]
    public function revenue30Days()
    {
        $start = Carbon::now()->subDays(30);

        return Cashier::formatAmount($this->team->payments()->where('completed_at', '>=', $start)->sum('amount'), $this->team->stripe_currency);
    }

    #[Computed]
    public function steps()
    {
        return [
            [
                'key' => 'branding',
                'label' => __('Configure branding settings'),
                'desc' => __('Upload your studio logos and visual identity.'),
                'action' => __('Configure branding'),
                'route' => route('branding.logos'),
                'icon' => 'paint-brush',
                'complete' => $this->isBrandingConfigured(),
            ],
            [
                'key' => 'watermark',
                'label' => __('Configure watermark settings'),
                'desc' => __('Protect your photos with custom watermarks.'),
                'action' => __('Configure watermark'),
                'route' => route('branding.watermark'),
                'icon' => 'sparkles',
                'complete' => $this->isWatermarkConfigured(),
            ],
            [
                'key' => 'portfolio',
                'label' => __('Set up your portfolio'),
                'desc' => __('Showcase your best work to attract clients.'),
                'action' => __('Set up portfolio'),
                'route' => route('portfolio'),
                'icon' => 'folder',
                'complete' => $this->isPortfolioComplete(),
            ],
            [
                'key' => 'biolink',
                'label' => __('Create your BioLink'),
                'desc' => __('Share a single link to all your socials and portfolio.'),
                'action' => __('Create BioLink'),
                'route' => route('public-profile'),
                'icon' => 'link',
                'complete' => $this->isBiolinkCreated(),
            ],
            [
                'key' => 'payments',
                'label' => __('Configure payment settings'),
                'desc' => __('Enable payments to get paid for your work.'),
                'action' => __('Configure payments'),
                'route' => route('branding.payments'),
                'icon' => 'credit-card',
                'complete' => $this->isPaymentsConfigured(),
            ],
        ];
    }

    #[Computed]
    public function team()
    {
        return Auth::user()->currentTeam;
    }

    #[Computed]
    public function totalGb()
    {
        return $this->team->hasUnlimitedStorage ? __('Unlimited') : $this->team->storage_limit_gb;
    }

    #[Computed]
    public function upcomingContractsAwaitingSignature()
    {
        return $this->team->contracts()
            ->whereNull('executed_at')
            ->orderBy('shooting_date')
            ->get();
    }

    #[Computed]
    public function upcomingPhotoshoots()
    {
        return $this->team->photoshoots()
            ->where('date', '>=', now()->copy()->startOfDay())
            ->orderBy('date')
            ->get();
    }

    #[Computed]
    public function usagePercent()
    {
        return $this->team->hasUnlimitedStorage ? null : $this->team->storage_used_percent;
    }

    #[Computed]
    public function usedGb()
    {
        return $this->team->storage_used_gb;
    }

    #[Computed]
    public function user()
    {
        return Auth::user();
    }

    public function dismissStep(string $step)
    {
        $this->team->dismissSetupStep($step);
        $this->team->refresh();
    }

    public function formatEventDate($date)
    {
        return $date->isoFormat('MMM D, YYYY');
    }

    public function isToday($date)
    {
        return $date->isSameDay(now());
    }

    public function getUpcomingEventsAndReminders()
    {
        $events = collect();

        foreach ($this->birthdaySoonCustomers as $customer) {
            $now = now()->startOfDay();

            $birthday = $customer->birthdate->copy()->year($now->year);

            if ($birthday->lt($now)) {
                $birthday->addYear();
            }

            $isToday = $this->isToday($birthday);
            $age = $isToday ? $customer->age : $customer->age + 1;

            $events->push([
                'type' => 'birthday',
                'label' => $customer->name,
                'date' => $birthday,
                'link' => "/customers/{$customer->id}",
                'is_today' => $isToday,
                'age' => $age,
            ]);
        }

        foreach ($this->upcomingPhotoshoots as $photoshoot) {
            $events->push([
                'type' => 'photoshoot',
                'label' => $photoshoot->name,
                'date' => $photoshoot->date,
                'link' => "/photoshoots/{$photoshoot->id}",
                'is_today' => $this->isToday($photoshoot->date),
            ]);
        }

        foreach ($this->expiringGalleries as $gallery) {
            $events->push([
                'type' => 'gallery',
                'label' => $gallery->name,
                'date' => $gallery->expiration_date,
                'link' => "/galleries/{$gallery->id}",
                'is_today' => $this->isToday($gallery->expiration_date),
            ]);
        }

        foreach ($this->upcomingContractsAwaitingSignature as $contract) {
            $events->push([
                'type' => 'contract',
                'label' => $contract->title ?? __('Contract'),
                'date' => $contract->shooting_date,
                'link' => "/contracts/{$contract->id}",
                'is_today' => $this->isToday($contract->shooting_date),
            ]);
        }

        return $events->sortBy('date')->values();
    }

    public function hasUpcomingEventsOrReminders()
    {
        return
            $this->birthdaySoonCustomers?->isNotEmpty() ||
            $this->upcomingPhotoshoots?->isNotEmpty() ||
            $this->expiringGalleries?->isNotEmpty() ||
            $this->upcomingContractsAwaitingSignature?->isNotEmpty();
    }

    public function isBiolinkCreated()
    {
        return $this->team->bioLinks()->exists();
    }

    public function isPaymentsConfigured()
    {
        return $this->team->hasCompletedOnboarding();
    }

    public function isPortfolioComplete()
    {
        return $this->team->galleries()->public()->exists();
    }

    public function isWatermarkConfigured()
    {
        return ! empty($this->team->brand_watermark_path);
    }

    public function isBrandingConfigured()
    {
        return ! empty($this->team->brand_logo_path);
    }
} ?>

<div>
    <flux:heading size="xl" level="1">
        {{ __('Welcome back, :name', ['name' => $this->user->name]) }}
    </flux:heading>

    <flux:spacer class="my-6" />

    <flux:modal.trigger name="search" shortcut="cmd.k">
        <flux:input as="button" :placeholder="__('Search...')" icon="magnifying-glass" kbd="⌘K" />
    </flux:modal.trigger>

    <flux:spacer class="my-6" />

    @if (! $this->team->subscribed())
        <flux:callout icon="shield-check" color="teal" inline>
            <flux:callout.heading>
                <flux:text variant="strong">
                    {{ __('Get More With Picstome Pro') }}
                </flux:text>
            </flux:callout.heading>
            <flux:callout.text>
                <flux:text variant="strong" class="font-medium">
                    {{ __('Unlock 1000GB storage, payments, gallery expiry dates, unlimited contracts, and white label branding. Upgrade to Pro and power up your business.') }}
                </flux:text>
            </flux:callout.text>
            <x-slot name="actions">
                <flux:button :href="route('subscribe')" variant="primary" color="teal">
                    {{ __('Upgrade to Pro') }}
                </flux:button>
            </x-slot>
        </flux:callout>
    @endif

    <flux:spacer class="my-6" />

    @if ($this->incompleteSteps->count())
        <section>
            <flux:heading size="lg">
                {{ __('Complete Your Account Setup') }}
            </flux:heading>
            <flux:spacer class="my-4" />
            <div class="space-y-2">
                @foreach ($this->incompleteSteps as $step)
                    @if (! $step['complete'])
                        <flux:callout
                            wire:key="{{ $step['key'] }}"
                            icon="{{ $step['icon'] }}"
                            variant="secondary"
                            inline
                        >
                            <flux:callout.heading>
                                {{ $step['label'] }}
                            </flux:callout.heading>
                            <x-slot name="actions">
                                <flux:button size="sm" :href="$step['route']">
                                    {{ $step['action'] }}
                                </flux:button>
                            </x-slot>
                            <x-slot name="controls">
                                <flux:button
                                    icon="x-mark"
                                    variant="subtle"
                                    wire:click="dismissStep('{{ $step['key'] }}')"
                                    size="sm"
                                />
                            </x-slot>
                        </flux:callout>
                    @endif
                @endforeach
            </div>
        </section>

        <flux:spacer class="my-6" />
    @endif

    <section>
        <div class="space-y-4">
            <flux:heading size="lg">{{ __('Account Overview') }}</flux:heading>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
                <a
                    href="{{ route('customers') }}"
                    wire:navigate
                    class="block rounded-lg bg-zinc-50 p-4 transition hover:bg-zinc-100 dark:bg-zinc-700 dark:hover:bg-zinc-600"
                >
                    <flux:subheading>{{ __('Customers') }}</flux:subheading>
                    <flux:heading size="xl">{{ $this->customersCount }}</flux:heading>
                </a>
                <a
                    href="{{ route('galleries') }}"
                    wire:navigate
                    class="block rounded-lg bg-zinc-50 p-4 transition hover:bg-zinc-100 dark:bg-zinc-700 dark:hover:bg-zinc-600"
                >
                    <flux:subheading>{{ __('Galleries') }}</flux:subheading>
                    <flux:heading size="xl">{{ $this->galleriesCount }}</flux:heading>
                </a>
                <a
                    href="{{ route('payments') }}"
                    wire:navigate
                    class="block rounded-lg bg-zinc-50 p-4 transition hover:bg-zinc-100 dark:bg-zinc-700 dark:hover:bg-zinc-600"
                >
                    <flux:subheading>{{ __('Revenue (30d)') }}</flux:subheading>
                    <flux:heading size="xl">{{ $this->revenue30Days }}</flux:heading>
                </a>
                <div class="rounded-lg bg-zinc-50 p-4 dark:bg-zinc-700">
                    <flux:subheading>{{ __('Storage Used') }}</flux:subheading>
                    <flux:heading size="xl">{{ $this->usedGb }}</flux:heading>
                    <flux:spacer class="mt-2" />
                    <div class="space-y-2">
                        @if (! $this->team->hasUnlimitedStorage)
                            <div class="h-1.5 w-full rounded-full bg-zinc-200 dark:bg-zinc-700">
                                <div
                                    class="{{ $this->usagePercent > 90 ? 'bg-red-500' : ($this->usagePercent > 75 ? 'bg-yellow-500' : 'bg-blue-500') }} h-1.5 rounded-full transition-all duration-300"
                                    style="width: {{ min($this->usagePercent, 100) }}%"
                                ></div>
                            </div>
                            <flux:text class="mt-2 text-[11px]">
                                {{ __(':used of :total used', ['used' => $this->usedGb, 'total' => $this->totalGb]) }}
                            </flux:text>
                        @else
                            <flux:text class="text-[11px]">
                                {{ __(':used used (Unlimited)', ['used' => $this->usedGb]) }}
                            </flux:text>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </section>

    @if ($this->hasUpcomingEventsOrReminders())
        <section class="mt-8 mb-8">
            <flux:heading size="lg">{{ __('Upcoming Events & Reminders') }}</flux:heading>
            <flux:separator class="mt-3" />
            <x-table>
                <x-table.rows>
                    @foreach ($this->getUpcomingEventsAndReminders() as $event)
                        <x-table.row :class="!empty($event['is_today']) && $event['is_today'] ? 'bg-yellow-50 dark:bg-yellow-900/30' : ''">
                            <x-table.cell variant="strong" class="relative w-full">
                                <a
                                    href="{{ $event['link'] }}"
                                    wire:navigate
                                    class="absolute inset-0 focus:outline-hidden"
                                ></a>
                                <div class="flex flex-wrap items-center gap-2">
                                    {{ $event['label'] }}
@if ($event['type'] === 'birthday')
    <flux:badge color="{{ !empty($event['is_today']) && $event['is_today'] ? 'green' : 'yellow' }}" inset="top bottom" icon="cake" size="sm">
        {{ !empty($event['is_today']) && $event['is_today'] ? __('Birthday today') : __('Birthday soon') }} ({{ $event['age'] }})
    </flux:badge>
@elseif ($event['type'] === 'photoshoot')
    <flux:badge color="{{ !empty($event['is_today']) && $event['is_today'] ? 'green' : 'blue' }}" inset="top bottom" icon="camera" size="sm">
        {{ !empty($event['is_today']) && $event['is_today'] ? __('Scheduled today') : __('Scheduled') }}
    </flux:badge>
@elseif ($event['type'] === 'gallery')
    <flux:badge color="{{ !empty($event['is_today']) && $event['is_today'] ? 'green' : 'red' }}" inset="top bottom" icon="clock" size="sm">
        {{ !empty($event['is_today']) && $event['is_today'] ? __('Expiring today') : __('Expiring soon') }}
    </flux:badge>
@elseif ($event['type'] === 'contract')
    <flux:badge color="orange" inset="top bottom" icon="document" size="sm">
        {{ __('Awaiting signature') }}
    </flux:badge>
@endif
                                </div>
                            </x-table.cell>
                            <x-table.cell class="relative" align="end">
                                <a
                                    href="{{ $event['link'] }}"
                                    wire:navigate
                                    class="absolute inset-0 focus:outline-hidden"
                                ></a>
                                {{ $this->formatEventDate($event['date']) }}
                            </x-table.cell>
                        </x-table.row>
                    @endforeach
                </x-table.rows>
            </x-table>
        </section>
    @endif
</div>
