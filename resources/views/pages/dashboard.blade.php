<?php

use Carbon\Carbon;
use Laravel\Cashier\Cashier;
use Livewire\Volt\Component;

use function Laravel\Folio\middleware;
use function Laravel\Folio\name;

name('dashboard');

middleware(['auth', 'verified']);

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;

new class extends Component
{
    #[Computed]
    public function team()
    {
        return Auth::user()->currentTeam;
    }

    #[Computed]
    public function usedGb()
    {
        return $this->team->storage_used_gb;
    }

    #[Computed]
    public function totalGb()
    {
        return $this->team->hasUnlimitedStorage ? __('Unlimited') : $this->team->storage_limit_gb;
    }

    #[Computed]
    public function usagePercent()
    {
        return $this->team->hasUnlimitedStorage ? null : $this->team->storage_used_percent;
    }

    #[Computed]
    public function user()
    {
        return Auth::user();
    }

    public function isPortfolioComplete()
    {
        return $this->team->galleries()->public()->exists();
    }

    public function isPaymentsConfigured()
    {
        return $this->team->hasCompletedOnboarding();
    }

    public function isBiolinkCreated()
    {
        return $this->team->bioLinks()->exists();
    }

    public function isWatermarkConfigured()
    {
        return ! empty($this->team->brand_watermark_path);
    }

    #[Computed]
    public function customersCount()
    {
        return $this->team->customers()->count();
    }

    #[Computed]
    public function galleriesCount()
    {
        return $this->team->galleries()->count();
    }

    #[Computed]
    public function revenue30Days()
    {
        $start = Carbon::now()->subDays(30);

        return Cashier::formatAmount($this->team->payments()->where('completed_at', '>=', $start)->sum('amount'), $this->team->stripe_currency);
    }

    #[Computed]
    public function birthdaySoonCustomers()
    {
        return $this->team->customers()
            ->get()
            ->filter(fn ($customer) => $customer->isBirthdaySoon())
            ->sortBy(function ($customer) {
                $now = now();
                $thisYearBirthday = $customer->birthdate->copy()->year($now->year);
                if ($thisYearBirthday->lt($now)) {
                    $thisYearBirthday->addYear();
                }

                return $now->diffInDays($thisYearBirthday, false);
            });
    }

    #[Computed]
    public function upcomingPhotoshoots()
    {
        return $this->team->photoshoots()
            ->whereDate('date', '>=', now()->toDateString())
            ->orderBy('date')
            ->get();
    }

    #[Computed]
    public function expiringGalleries()
    {
        return $this->team->galleries()
            ->whereNotNull('expiration_date')
            ->where('expiration_date', '>=', now()->toDateString())
            ->where('expiration_date', '<=', now()->addDays(30)->toDateString())
            ->orderBy('expiration_date')
            ->get();
    }

    #[Computed]
    public function upcomingContractsAwaitingSignature()
    {
        return $this->team->contracts()
            ->whereDate('shooting_date', '>=', now()->toDateString())
            ->whereNull('executed_at')
            ->orderBy('shooting_date')
            ->get();
    }

    #[Computed]
    public function steps()
    {
        return [
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
                'key' => 'payments',
                'label' => __('Configure payment settings'),
                'desc' => __('Enable payments to get paid for your work.'),
                'action' => __('Configure payments'),
                'route' => route('branding.payments'),
                'icon' => 'credit-card',
                'complete' => $this->isPaymentsConfigured(),
            ],
            [
                'key' => 'biolink',
                'label' => __('Create your first BioLink'),
                'desc' => __('Share a single link to all your socials and portfolio.'),
                'action' => __('Create BioLink'),
                'route' => route('public-profile'),
                'icon' => 'link',
                'complete' => $this->isBiolinkCreated(),
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
        ];
    }

    public function dismissStep(string $step)
    {
        $this->team->dismissSetupStep($step);
        $this->team->refresh();
    }

    #[Computed]
    public function incompleteSteps()
    {
        $dismissed = $this->team->dismissed_setup_steps ?? [];

        return collect($this->steps)
            ->where('complete', false)
            ->reject(fn ($step) => in_array($step['key'], $dismissed));
    }
} ?>

<x-app-layout>
    @volt('pages.dashboard')
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
                <flux:callout icon="shield-check">
                    <flux:callout.heading>{{ __('Get More With Premium') }}</flux:callout.heading>
                    <flux:callout.text>
                        {{ __('Unlock 1000GB storage, payments, gallery expiry dates, unlimited contracts, and white label branding. Upgrade to Premium and power up your business.') }}
                    </flux:callout.text>
                    <x-slot name="actions">
                        <flux:button :href="route('subscribe')" variant="primary">
                            {{ __('Upgrade to Premium') }}
                        </flux:button>
                    </x-slot>
                </flux:callout>
            @endif

            <flux:spacer class="my-6" />

            @if ($this->incompleteSteps->count())
                <section>
                    <flux:heading size="lg">
                        {{ __('Complete your account setup') }}
                    </flux:heading>
                    <flux:spacer class="my-4" />
                    <div class="space-y-2">
                        @foreach ($this->steps as $step)
                            @if (! $step['complete'])
                                <flux:callout icon="{{ $step['icon'] }}" variant="secondary" inline>
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

                <section>
                    <div class="space-y-4">
                        <flux:heading size="lg">{{ __('Account Stats') }}</flux:heading>
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
                                        <flux:text class="mt-2 text-[11px]!">
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
            @endif

            @if (
                $this->birthdaySoonCustomers?->isNotEmpty() ||
                $this->upcomingPhotoshoots?->isNotEmpty() ||
                $this->expiringGalleries?->isNotEmpty() ||
                $this->upcomingContractsAwaitingSignature?->isNotEmpty()
            )
                <section class="mt-8 mb-8">
                    <flux:heading size="lg">{{ __('Upcoming Events & Reminders') }}</flux:heading>
                    <flux:separator class="mt-3" />

                    @if ($this->birthdaySoonCustomers?->isNotEmpty())
                        <div class="mb-8">
                            <x-heading level="2">{{ __('Upcoming Birthdays') }}</x-heading>
                            <x-table>
                                <x-table.rows>
                                    @foreach ($this->birthdaySoonCustomers as $customer)
                                        <x-table.row>
                                            <x-table.cell variant="strong" class="relative w-full">
                                                <a
                                                    href="/customers/{{ $customer->id }}"
                                                    wire:navigate
                                                    class="absolute inset-0 focus:outline-hidden"
                                                ></a>
                                                <div class="flex items-center gap-2">
                                                    {{ $customer->name }}
                                                    <flux:badge color="yellow" inset="top bottom" icon="cake" size="sm">
                                                        {{ __('Birthday soon') }}
                                                    </flux:badge>
                                                </div>
                                            </x-table.cell>
                                            <x-table.cell class="relative" align="end">
                                                <a
                                                    href="/customers/{{ $customer->id }}"
                                                    wire:navigate
                                                    class="absolute inset-0 focus:outline-hidden"
                                                ></a>
                                                {{ $customer->email }}
                                            </x-table.cell>
                                            <x-table.cell class="relative" align="end">
                                                <a
                                                    href="/customers/{{ $customer->id }}"
                                                    wire:navigate
                                                    class="absolute inset-0 focus:outline-hidden"
                                                ></a>
                                                {{ $customer->phone }}
                                            </x-table.cell>
                                            <x-table.cell class="relative" align="end">
                                                <a
                                                    href="/customers/{{ $customer->id }}"
                                                    wire:navigate
                                                    class="absolute inset-0 focus:outline-hidden"
                                                ></a>
                                                @php $bday = $customer->birthdate->copy()->year(now()->year); if ($bday->lt(now())) $bday->addYear(); @endphp {{ $bday->isToday() ? __('Today') : ($bday->isTomorrow() ? __('Tomorrow') : $bday->diffForHumans(now(), ['parts' => 1, 'short' => true])) }}
                                            </x-table.cell>
                                        </x-table.row>
                                    @endforeach
                                </x-table.rows>
                            </x-table>
                        </div>
                    @endif

                    @if ($this->upcomingPhotoshoots?->isNotEmpty())
                        <div class="mb-8">
                            <x-heading level="2">{{ __('Upcoming Photoshoots') }}</x-heading>
                            <x-table>
                                <x-table.rows>
                                    @foreach ($this->upcomingPhotoshoots as $photoshoot)
                                        <x-table.row>
                                            <x-table.cell variant="strong" class="relative w-full">
                                                <a
                                                    href="/photoshoots/{{ $photoshoot->id }}"
                                                    wire:navigate
                                                    class="absolute inset-0 focus:outline-hidden"
                                                ></a>
                                                <div class="flex items-center gap-2">
                                                    {{ $photoshoot->title ?? __('Photoshoot') }}
                                                    <flux:badge color="blue" inset="top bottom" icon="camera" size="sm">
                                                        {{ __('Scheduled') }}
                                                    </flux:badge>
                                                </div>
                                            </x-table.cell>
                                            <x-table.cell class="relative" align="end">
                                                <a
                                                    href="/photoshoots/{{ $photoshoot->id }}"
                                                    wire:navigate
                                                    class="absolute inset-0 focus:outline-hidden"
                                                ></a>
                                                {{ $photoshoot->date->isToday() ? __('Today') : ($photoshoot->date->isTomorrow() ? __('Tomorrow') : $photoshoot->date->diffForHumans(now(), ['parts' => 1, 'short' => true])) }}
                                            </x-table.cell>
                                            <x-table.cell class="relative" align="end">
                                                <a
                                                    href="/photoshoots/{{ $photoshoot->id }}"
                                                    wire:navigate
                                                    class="absolute inset-0 focus:outline-hidden"
                                                ></a>
                                                {{ $photoshoot->location ?? '-' }}
                                            </x-table.cell>
                                        </x-table.row>
                                    @endforeach
                                </x-table.rows>
                            </x-table>
                        </div>
                    @endif

                    @if ($this->expiringGalleries?->isNotEmpty())
                        <div class="mb-8">
                            <x-heading level="2">{{ __('Expiring Galleries') }}</x-heading>
                            <x-table>
                                <x-table.rows>
                                    @foreach ($this->expiringGalleries as $gallery)
                                        <x-table.row>
                                            <x-table.cell variant="strong" class="relative w-full">
                                                <a
                                                    href="/galleries/{{ $gallery->id }}"
                                                    wire:navigate
                                                    class="absolute inset-0 focus:outline-hidden"
                                                ></a>
                                                <div class="flex items-center gap-2">
                                                    {{ $gallery->name }}
                                                    <flux:badge color="red" inset="top bottom" icon="clock" size="sm">
                                                        {{ __('Expiring soon') }}
                                                    </flux:badge>
                                                </div>
                                            </x-table.cell>
                                            <x-table.cell class="relative" align="end">
                                                <a
                                                    href="/galleries/{{ $gallery->id }}"
                                                    wire:navigate
                                                    class="absolute inset-0 focus:outline-hidden"
                                                ></a>
                                                {{ $gallery->expiration_date->isToday() ? __('Today') : ($gallery->expiration_date->isTomorrow() ? __('Tomorrow') : $gallery->expiration_date->diffForHumans(now(), ['parts' => 1, 'short' => true])) }}
                                            </x-table.cell>
                                            <x-table.cell class="relative" align="end">
                                                <a
                                                    href="/galleries/{{ $gallery->id }}"
                                                    wire:navigate
                                                    class="absolute inset-0 focus:outline-hidden"
                                                ></a>
                                                {{ $gallery->photoshoot?->title ?? '-' }}
                                            </x-table.cell>
                                        </x-table.row>
                                    @endforeach
                                </x-table.rows>
                            </x-table>
                        </div>
                    @endif

                    @if ($this->upcomingContractsAwaitingSignature?->isNotEmpty())
                        <div>
                            <x-heading level="2">{{ __('Upcoming Contracts Awaiting Signature') }}</x-heading>
                            <x-table>
                                <x-table.rows>
                                    @foreach ($this->upcomingContractsAwaitingSignature as $contract)
                                        <x-table.row>
                                            <x-table.cell variant="strong" class="relative w-full">
                                                <a
                                                    href="/contracts/{{ $contract->id }}"
                                                    wire:navigate
                                                    class="absolute inset-0 focus:outline-hidden"
                                                ></a>
                                                <div class="flex items-center gap-2">
                                                    {{ $contract->title ?? __('Contract') }}
                                                    <flux:badge color="orange" inset="top bottom" icon="document" size="sm">
                                                        {{ __('Awaiting signature') }}
                                                    </flux:badge>
                                                </div>
                                            </x-table.cell>
                                            <x-table.cell class="relative" align="end">
                                                <a
                                                    href="/contracts/{{ $contract->id }}"
                                                    wire:navigate
                                                    class="absolute inset-0 focus:outline-hidden"
                                                ></a>
                                                {{ $contract->shooting_date->isToday() ? __('Today') : ($contract->shooting_date->isTomorrow() ? __('Tomorrow') : $contract->shooting_date->diffForHumans(now(), ['parts' => 1, 'short' => true])) }}
                                            </x-table.cell>
                                            <x-table.cell class="relative" align="end">
                                                <a
                                                    href="/contracts/{{ $contract->id }}"
                                                    wire:navigate
                                                    class="absolute inset-0 focus:outline-hidden"
                                                ></a>
                                                {{ $contract->photoshoot?->title ?? '-' }}
                                            </x-table.cell>
                                        </x-table.row>
                                    @endforeach
                                </x-table.rows>
                            </x-table>
                        </div>
                    @endif
                </section>
            @endif
        </div>
    @endvolt
</x-app-layout>
