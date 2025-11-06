<?php

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
            @endif
        </div>
    @endvolt
</x-app-layout>
