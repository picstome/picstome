<?php

use Facades\App\Services\StripeConnectService;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

use function Laravel\Folio\name;

name('stripe.connect.return');

new class extends Component {
    public $onboardingUrl = null;

    public $onboardingComplete = false;

    public function mount()
    {
        $team = Auth::user()->currentTeam;

        $this->onboardingComplete = $team->hasCompletedOnboarding();

        if (!$team->hasCompletedOnboarding() && StripeConnectService::isOnboardingComplete($team)) {
            $team->markOnboarded();

            $this->onboardingComplete = true;
        }

        if (!$this->onboardingComplete) {
            $this->onboardingUrl = StripeConnectService::createOnboardingLink($team);
        }
    }
} ?>

<x-app-layout>
    @volt('pages.stripe-connect.return')
        <section class="mx-auto max-w-lg mt-10">
            <div class="relative mb-6 w-full">
                <flux:heading size="xl" level="1">Stripe Connect</flux:heading>
                <flux:subheading size="lg">
                    Check your Stripe onboarding status and start accepting payments.
                </flux:subheading>
                <flux:separator variant="subtle" class="mt-6" />
            </div>

            @if ($onboardingComplete)
                <flux:callout variant="secondary" icon="check-circle">
                    <flux:callout.heading>Stripe onboarding complete!</flux:callout.heading>
                    <flux:callout.text>
                        Your Stripe account is now connected. You can start accepting payments.
                    </flux:callout.text>
                </flux:callout>
            @else
                <flux:callout variant="secondary" icon="exclamation-circle">
                    <flux:callout.heading>Stripe onboarding not complete</flux:callout.heading>
                    <flux:callout.text>
                        You have not finished connecting your Stripe account. Please complete onboarding to accept payments.
                    </flux:callout.text>
                    <x-slot name="actions">
                        <flux:button href="{{ $onboardingUrl }}" variant="primary" target="_blank">Continue Stripe Onboarding</flux:button>
                    </x-slot>
                </flux:callout>
            @endif
        </section>
    @endvolt
</x-app-layout>
