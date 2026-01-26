<?php
use Facades\App\Services\StripeConnectService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts.app')] class extends Component
{
    public $onboardingUrl;

    public function mount()
    {
        $team = Auth::user()->currentTeam;

        $this->onboardingUrl = StripeConnectService::createOnboardingLink($team);
    }
} ?>

<section class="mx-auto max-w-lg">
    <div class="relative mb-6 w-full">
        <flux:heading size="xl" level="1">{{ __("Stripe Connect") }}</flux:heading>
        <flux:subheading size="lg">
            {{ __("Connect your account to Stripe to start accepting payments.") }}
        </flux:subheading>
        <flux:separator variant="subtle" class="mt-6" />
    </div>
    <flux:callout icon="banknotes" variant="secondary">
        <flux:callout.heading>{{ __("Start accepting payments") }}</flux:callout.heading>
        <flux:callout.text>
            {{ __("To begin accepting payments, you need to complete your Stripe onboarding. Click the button below to get started or continue the process.") }}
        </flux:callout.text>
        <x-slot name="actions">
            <flux:button href="{{ $onboardingUrl }}" variant="primary" target="_blank">{{ __("Begin Stripe Onboarding") }}</flux:button>
        </x-slot>
    </flux:callout>
</section>
