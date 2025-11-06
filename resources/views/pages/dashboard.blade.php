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
    public function user()
    {
        return Auth::user();
    }

    #[Computed]
    public function portfolioComplete()
    {
        return $this->team->galleries()->public()->exists();
    }

    #[Computed]
    public function paymentsConfigured()
    {
        return $this->team->hasCompletedOnboarding();
    }

    #[Computed]
    public function biolinkCreated()
    {
        return $this->team->bioLinks()->exists();
    }

    #[Computed]
    public function watermarkConfigured()
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
                'complete' => $this->portfolioComplete,
            ],
            [
                'key' => 'payments',
                'label' => __('Configure payment settings'),
                'desc' => __('Enable payments to get paid for your work.'),
                'action' => __('Configure payments'),
                'route' => route('branding.payments'),
                'icon' => 'credit-card',
                'complete' => $this->paymentsConfigured,
            ],
            [
                'key' => 'biolink',
                'label' => __('Create your first BioLink'),
                'desc' => __('Share a single link to all your socials and portfolio.'),
                'action' => __('Create BioLink'),
                'route' => route('public-profile'),
                'icon' => 'link',
                'complete' => $this->biolinkCreated,
            ],
            [
                'key' => 'watermark',
                'label' => __('Configure watermark settings'),
                'desc' => __('Protect your photos with custom watermarks.'),
                'action' => __('Configure watermark'),
                'route' => route('branding.watermark'),
                'icon' => 'sparkles',
                'complete' => $this->watermarkConfigured,
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
                        <flux:button :href="route('subscribe')" variant="primary">{{ __('Upgrade to Premium') }}</flux:button>
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
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                            <div class="rounded-lg bg-zinc-50 p-4 dark:bg-zinc-700">
                                <flux:subheading>{{ __('Customers') }}</flux:subheading>
                                <flux:heading size="xl">{{ $this->customersCount }}</flux:heading>
                            </div>
                            <div class="rounded-lg bg-zinc-50 p-4 dark:bg-zinc-700">
                                <flux:subheading>{{ __('Galleries') }}</flux:subheading>
                                <flux:heading size="xl">{{ $this->galleriesCount }}</flux:heading>
                            </div>
                            <div class="rounded-lg bg-zinc-50 p-4 dark:bg-zinc-700">
                                <flux:subheading>{{ __('Revenue (30d)') }}</flux:subheading>
                                <flux:heading size="xl">{{ $this->revenue30Days }}</flux:heading>
                            </div>
                            <div class="rounded-lg bg-zinc-50 p-4 dark:bg-zinc-700">
                                @livewire('storage-usage-indicator')
                            </div>
                        </div>
                    </div>
                </section>
            @endif
        </div>
    @endvolt
</x-app-layout>
